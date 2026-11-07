<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Kopling\SportsManagement\AvailabilityStatus;
use Kopling\SportsManagement\MatchGoal;
use Kopling\SportsManagement\MatchSanction;
use Kopling\SportsManagement\MatchSubstitution;
use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\RefereeDuty;
use Kopling\SportsManagement\SubstitutionDirection;
use Kopling\SportsManagement\TeamMatch;
use Kopling\SportsManagement\TeamMember;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Base for the live tracking tools: same permission and duty checks as the tracking screen, and the match state as answer.
 */
abstract class MatchTool extends TeamTool
{
    protected ?string $permission = 'kopling-sports-management::track-matches';

    /**
     * @param RefereeDuty|null $duty null for coach-only actions (substitutions)
     */
    protected function trackedMatch(Request $request, ?RefereeDuty $duty): TeamMatch
    {
        $team = $this->team($request);
        $match = $this->match($request, $team)->setRelation('team', $team);
        $this->authorizeDuty($match, $duty);

        return $match;
    }

    /**
     * @param RefereeDuty|null $duty null for coach-only actions (substitutions)
     */
    protected function authorizeDuty(TeamMatch $match, ?RefereeDuty $duty): void
    {
        $person = $this->person();
        $allowed = $duty === null ? $match->team->isCoachedBy($person) : $match->handles($person, $duty);

        if (! $allowed) {
            throw new AuthorizationException($duty === null || ! $match->delegates($duty)
                ? 'Only the team\'s coaches can do this.'
                : 'The referee handles this for this match.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function matchSchema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'match_id' => $schema->string()->description('The match id, from list-matches.')->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function minuteSchema(JsonSchema $schema): array
    {
        return ['minute' => $schema->integer()->description('Match minute, only for something that happened earlier; leave out for "just now".')];
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function respond(TeamMatch $match, array $extra = []): Response
    {
        return Response::json([...array_filter($extra, fn ($value) => $value !== null), ...self::state($match)]);
    }

    /**
     * Who is on the field and on the bench with their ids, the clock, the score, and the latest events.
     *
     * @return array<string, mixed>
     */
    public static function state(TeamMatch $match): array
    {
        $team = $match->team;
        $match = TeamMatch::with(['formatPreset', 'availabilities', 'periods', 'substitutions', 'goals', 'sanctions', 'fieldSizes'])->findOrFail($match->id)->setRelation('team', $team);
        $config = $match->sportConfig();
        $preset = $match->effectiveFormatPreset();
        $timeline = $match->timeline();
        $running = $timeline->runningPeriod();
        $onField = $timeline->onField();
        $played = $timeline->playedSeconds();
        $penalties = $timeline->penaltySecondsLeft();
        $unavailable = $match->unavailableMemberIds($timeline);
        $absent = $match->availabilities->where('status', AvailabilityStatus::Absent)->pluck('team_member_id')->all();

        $members = TeamMember::sorted($team->members()->onTeamAt($match->scheduled_at)->with('person')->get())
            ->reject(fn (TeamMember $member) => in_array($member->id, $absent, true) && ! array_key_exists($member->id, $onField))
            ->keyBy('id');
        $started = $match->periods->isNotEmpty();
        $now = $timeline->matchSeconds();
        $moved = $timeline->lastMoves();
        $target = $match->fairShareSeconds($members->count());
        $player = fn (TeamMember $member) => array_filter([
            'id' => $member->id,
            'name' => $member->person->name,
            'jersey_number' => $member->jersey_number,
            'zone' => ($zone = $onField[$member->id] ?? null) ? $zone->value : null,
            'minutes_played' => intdiv($played[$member->id] ?? 0, 60),
            'minutes_on_bench' => $started ? intdiv($now - ($played[$member->id] ?? 0), 60) : null,
            'stint_minutes' => $started ? intdiv($now - ($moved[$member->id] ?? 0), 60) : null,
            'minutes_to_target' => $target === null ? null : intdiv($target - ($played[$member->id] ?? 0), 60),
            'penalty_seconds_left' => $penalties[$member->id] ?? null,
            'unavailable' => in_array($member->id, $unavailable, true) ?: null,
        ], fn ($value) => $value !== null);

        return array_filter([
            'match_id' => $match->id,
            'opponent' => $match->opponent_name,
            'state' => $timeline->state()->value,
            'score' => $timeline->score(),
            'minute' => $match->periods->isEmpty() ? null : intdiv($timeline->matchSeconds(), 60),
            'running_period' => $running ? ['type' => $running->type->value, 'label' => $timeline->label($running)] : null,
            'max_on_field' => $match->effectiveMaxOnField($timeline),
            'equal_share' => self::equalShare($match, $members->count()),
            'on_field' => $members->filter(fn (TeamMember $member) => array_key_exists($member->id, $onField))->map($player)->values()->all(),
            'bench' => $members->reject(fn (TeamMember $member) => array_key_exists($member->id, $onField))->map($player)->values()->all(),
            'zones' => collect($config->zones($preset))->mapWithKeys(fn (Position $zone) => [$zone->value => $zone->label($team->sport)])->all(),
            'sanction_kinds' => array_map(fn ($kind) => $kind->value, $config->sanctions()) ?: null,
            'point_values' => count($config->pointValues()) > 1 ? $config->pointValues() : null,
            'latest_events' => $match->periods->isEmpty() ? null : self::events($match, $members)->take(10)->values()->all(),
        ], fn ($value) => $value !== null);
    }

    /**
     * @param Collection<string, TeamMember> $members
     * @return Collection<int, array<string, mixed>>
     */
    private static function events(TeamMatch $match, Collection $members): Collection
    {
        $timeline = $match->timeline();
        $periods = $match->periods->keyBy('id');
        $name = fn (?string $id) => $id === null ? null : $members->get($id)?->person->name;
        $minute = fn ($event) => intdiv($timeline->matchSecond($periods[$event->period_id], $event->offset_seconds), 60);

        return collect()
            ->concat($match->goals->map(fn (MatchGoal $goal) => [
                'id' => $goal->id, 'type' => 'goal', 'minute' => $minute($goal), 'at' => $goal->created_at,
                'for' => $goal->opponent ? 'opponent' : 'us', 'points' => $goal->points ?? 1,
                'scorer' => $name($goal->scorer_team_member_id), 'assist' => $name($goal->assist_team_member_id), 'own_goal' => $goal->own_goal ?: null,
            ]))
            ->concat($match->substitutions->reject(fn (MatchSubstitution $substitution) => $substitution->offset_seconds === 0 && $substitution->period_id === $match->periods->first()->id)->map(fn (MatchSubstitution $substitution) => [
                'id' => $substitution->id, 'type' => $substitution->direction === SubstitutionDirection::On ? 'on' : 'off', 'minute' => $minute($substitution), 'at' => $substitution->created_at,
                'player' => $name($substitution->team_member_id), 'zone' => $substitution->zone?->value,
            ]))
            ->concat($match->sanctions->map(fn (MatchSanction $sanction) => [
                'id' => $sanction->id, 'type' => 'sanction', 'minute' => $minute($sanction), 'at' => $sanction->created_at,
                'player' => $name($sanction->team_member_id), 'kind' => $sanction->kind->value,
            ]))
            ->sortByDesc(fn (array $event) => [$event['minute'], $event['at']])
            ->map(fn (array $event) => array_filter(array_diff_key($event, ['at' => true]), fn ($value) => $value !== null));
    }
}
