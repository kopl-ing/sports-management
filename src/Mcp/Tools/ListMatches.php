<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Mcp\TeamAccess;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMatch;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListMatches extends TeamTool
{
    protected string $name = 'list-matches';

    protected string $description = 'A team\'s matches, by date, with opponent, home or away, kick-off (scheduled_at, the club\'s local time without time zone), season period, state and score. Referees only see the matches they referee.';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'from' => $schema->string()->format('date')->description('Only matches on or after this date (YYYY-MM-DD).'),
            'until' => $schema->string()->format('date')->description('Only matches on or before this date (YYYY-MM-DD).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $team = $this->team($request);
        $team->load('periods');
        $person = $this->person();

        $matches = $team->matches()
            ->unless(TeamAccess::coaches($person, $team), fn ($query) => $query->where('referee_person_id', $person->id))
            ->when($data['from'] ?? null, fn ($query, $from) => $query->where('scheduled_at', '>=', $from))
            ->when($data['until'] ?? null, fn ($query, $until) => $query->where('scheduled_at', '<', CarbonImmutable::parse($until)->addDay()))
            ->with(['periods', 'goals'])
            ->orderBy('scheduled_at')
            ->get();

        return Response::json($matches->map(fn (TeamMatch $match) => self::summary($team, $match))->values()->all());
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Team $team, TeamMatch $match): array
    {
        $timeline = $match->timeline();
        $state = $timeline->state();

        return array_filter([
            'id' => $match->id,
            'opponent' => $match->opponent_name,
            'home_away' => $match->home_away->value,
            'scheduled_at' => $match->scheduled_at->format('Y-m-d\TH:i'),
            'period' => $team->periodOn($match->scheduled_at)?->name,
            'location' => $match->location_address,
            'state' => $state->value,
            'score' => $match->periods->isEmpty() ? null : $timeline->score(),
        ], fn ($value) => $value !== null);
    }
}
