<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\OAuth\Person;
use Kopling\SportsManagement\MatchState;
use Kopling\SportsManagement\Mcp\TeamAccess;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\TaskBalance as Balance;
use Kopling\SportsManagement\TaskOccurrence;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMatch;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class TeamStats extends TeamTool
{
    protected string $name = 'team-stats';

    protected string $description = 'Site admins only: figures per team (results, roster size, how often task dates reached their minimum, spread of task points), for one team or every team in a season.';

    protected function allows(Person $person): bool
    {
        return TeamAccess::isAdmin($person);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'team_id' => $schema->string()->description('One team; leave out for every team.'),
            'season' => $schema->string()->description('Only teams of this season, e.g. "2026/2027".'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate(['team_id' => ['nullable', 'uuid'], 'season' => ['nullable', 'string', 'max:50']]);

        $teams = isset($data['team_id'])
            ? collect([$this->team($request)])
            : Team::withTrashed()->when($data['season'] ?? null, fn ($query, $season) => $query->where('season', $season))->orderBy('name')->get();

        return Response::json($teams->map(fn (Team $team) => self::stats($team))->values()->all());
    }

    /**
     * @return array<string, mixed>
     */
    private static function stats(Team $team): array
    {
        $played = $team->matches()->with(['periods', 'goals'])->get()
            ->filter(fn (TeamMatch $match) => MatchState::fromPeriods($match->periods) === MatchState::Ended)
            ->map(fn (TeamMatch $match) => $match->timeline()->score());

        $dates = TaskOccurrence::query()
            ->whereHas('task', fn ($query) => $query->where('team_id', $team->id)->whereNotNull('min_people'))
            ->whereNull('cancelled_at')
            ->whereDate('date', '<', today()->toDateString())
            ->with('task.wholeTaskEnlistments')
            ->withCount('enlistments')
            ->get();
        $covered = $dates->filter(fn (TaskOccurrence $occurrence) => $occurrence->enlistments_count
            + $occurrence->task->wholeTaskEnlistments->filter->covers($occurrence)->count() >= $occurrence->task->min_people);

        $points = $team->tasks_enabled ? Balance::for($team)->reject(fn (array $row) => $row['left'])->pluck('points') : collect();

        return array_filter([
            'team_id' => $team->id,
            'team' => $team->name,
            'season' => $team->season,
            'matches_played' => $played->count(),
            'won' => $played->filter(fn (array $score) => $score['us'] > $score['them'])->count(),
            'drawn' => $played->filter(fn (array $score) => $score['us'] === $score['them'])->count(),
            'lost' => $played->filter(fn (array $score) => $score['us'] < $score['them'])->count(),
            'goals_for' => $played->sum('us'),
            'goals_against' => $played->sum('them'),
            'players' => $team->members()->onTeamAt(now())->where('guest', false)->count(),
            'task_dates_past' => $dates->count(),
            'task_dates_at_minimum' => $covered->count(),
            'task_points_min' => $points->min(),
            'task_points_max' => $points->max(),
        ], fn ($value) => $value !== null);
    }
}
