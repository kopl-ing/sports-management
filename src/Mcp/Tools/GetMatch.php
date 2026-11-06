<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\MatchGoal;
use Kopling\SportsManagement\MatchLineup;
use Kopling\SportsManagement\Mcp\TeamAccess;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\TeamMember;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetMatch extends TeamTool
{
    protected string $name = 'get-match';

    protected string $description = 'One match in full: its format (players on the field, play periods and their length, substitution rules), availability, starting lineup and the zones it uses, referee, and goals with minute and scorer once played.';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'match_id' => $schema->string()->description('The match id, from list-matches.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $team = $this->team($request);
        $match = $team->matches()->whereKey((string) $request->get('match_id'))->first();
        if ($match === null || ! (TeamAccess::coaches($this->person(), $team) || $match->isRefereedBy($this->person()))) {
            throw new AuthorizationException('Match not found.');
        }

        $team->load(['periods', 'members' => fn ($query) => $query->onTeamAt($match->scheduled_at)->with('person')]);
        $match->setRelation('team', $team)->load(['availabilities', 'lineup', 'periods', 'substitutions', 'goals', 'sanctions', 'referee']);
        $timeline = $match->timeline();
        $periods = $timeline->periods()->keyBy('id');
        $members = $team->members->keyBy('id');
        $name = fn (?string $id) => $id === null ? null : $members->get($id)?->person->name;

        return Response::json(array_filter([
            ...ListMatches::summary($team, $match),
            'format' => self::formatData($match->effectiveFormatPreset(), $match->effectivePlayMinutes()),
            'referee' => $match->referee?->name,
            'referee_duties' => $match->referee ? collect($match->referee_duties)->map->value->all() : null,
            'availability' => TeamMember::sorted($team->members)->map(fn (TeamMember $member) => [
                'player' => $member->person->name,
                'status' => $match->availabilities->firstWhere('team_member_id', $member->id)?->status->value,
            ])->values()->all(),
            'zones' => collect($match->sportConfig()->zones($match->effectiveFormatPreset()))->mapWithKeys(fn (Position $zone) => [$zone->value => $zone->label($team->sport)])->all(),
            'lineup' => $match->lineup->map(fn (MatchLineup $slot) => ['player' => $name($slot->team_member_id), 'zone' => $slot->zone?->value])->values()->all(),
            'goals' => $match->periods->isEmpty() ? null : $match->goals->map(fn (MatchGoal $goal) => array_filter([
                'minute' => intdiv($timeline->matchSecond($periods[$goal->period_id], $goal->offset_seconds), 60),
                'for' => $goal->opponent ? 'opponent' : 'us',
                'points' => $goal->points ?? 1,
                'scorer' => $name($goal->scorer_team_member_id),
                'assist' => $name($goal->assist_team_member_id),
                'own_goal' => $goal->own_goal ?: null,
            ], fn ($value) => $value !== null))->sortBy('minute')->values()->all(),
        ], fn ($value) => $value !== null));
    }
}
