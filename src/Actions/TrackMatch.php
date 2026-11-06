<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\FieldMove;
use Kopling\SportsManagement\MatchGoal;
use Kopling\SportsManagement\MatchPeriod;
use Kopling\SportsManagement\MatchSanction;
use Kopling\SportsManagement\PeriodType;
use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\SanctionKind;
use Kopling\SportsManagement\SubstitutionDirection;
use Kopling\SportsManagement\TeamMatch;

/**
 * Records what happens during a match, for the tracking screen and the MCP tools alike; authorization stays with the caller.
 */
class TrackMatch
{
    public const REPEAT_SECONDS = 5;

    /**
     * Starting a period live ends whichever period is still running.
     *
     * @param array<string, mixed> $input
     * @return MatchPeriod|null null when the same period type was started moments ago
     */
    public static function startPeriod(TeamMatch $match, array $input): ?MatchPeriod
    {
        $data = Validator::validate($input, ['type' => ['required', Rule::enum(PeriodType::class)]]);

        $latest = $match->periods()->reorder('sequence', 'desc')->first();
        if ($latest?->type->value === $data['type'] && $latest->started_at?->diffInSeconds(now()) < self::REPEAT_SECONDS) {
            return null;
        }

        if ($latest === null && ! self::lineupComplete($match)) {
            throw ValidationException::withMessages(['lineup' => __('kopling-sports-management::messages.lineup_incomplete')]);
        }

        return DB::transaction(function () use ($match, $data, $latest) {
            $timeline = $match->timeline();
            if ($running = $timeline->runningPeriod()) {
                $running->update(['duration_seconds' => $timeline->length($running)]);
            }

            $period = self::createPeriod($match, ['type' => $data['type'], 'started_at' => now()]);
            if ($latest === null) {
                self::addStarters($match, $period);
            }

            return $period;
        });
    }

    public static function endPeriod(TeamMatch $match, MatchPeriod $period): void
    {
        if ($period->isRunning()) {
            $period->update(['duration_seconds' => $match->timeline()->length($period)]);
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function addPeriod(TeamMatch $match, array $input): MatchPeriod
    {
        $data = Validator::validate($input, [
            'type' => ['required', Rule::enum(PeriodType::class)],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:240'],
        ]);

        return DB::transaction(function () use ($match, $data) {
            $first = $match->periods()->doesntExist();
            $period = self::createPeriod($match, ['type' => $data['type'], 'duration_seconds' => $data['duration_minutes'] * 60]);
            if ($first) {
                self::addStarters($match, $period);
            }

            return $period;
        });
    }

    /**
     * Either side may be left empty: a starter coming on, or a player leaving without replacement.
     *
     * @param array<string, mixed> $input
     * @return array<int, string> ids of the substitutions created
     */
    public static function substitute(TeamMatch $match, array $input): array
    {
        $data = Validator::validate($input, [
            ...self::eventRules(),
            'off_team_member_id' => ['nullable', 'required_without:on_team_member_id', 'different:on_team_member_id', self::memberOf($match)],
            'on_team_member_id' => ['nullable', self::memberOf($match)],
            'zone' => ['nullable', 'required_with:on_team_member_id', Rule::enum(Position::class)->only(self::zones($match))],
        ]);

        [$period, $offset] = self::moment($match, $data['minute'] ?? null);

        return DB::transaction(function () use ($match, $period, $offset, $data) {
            $ids = [];
            foreach ([SubstitutionDirection::Off->value => $data['off_team_member_id'] ?? null, SubstitutionDirection::On->value => $data['on_team_member_id'] ?? null] as $direction => $memberId) {
                if ($memberId !== null) {
                    $ids[] = $match->substitutions()->create([
                        'period_id' => $period->id,
                        'team_member_id' => $memberId,
                        'direction' => $direction,
                        'zone' => $direction === SubstitutionDirection::On->value ? $data['zone'] : null,
                        'offset_seconds' => $offset,
                    ])->id;
                }
            }

            return $ids;
        });
    }

    /**
     * Applies at "now" in the running period, or at the end of the last one when none is running.
     *
     * @param array<string, mixed> $input see `FieldMove::rules()`
     * @return array<int, string> ids of the substitutions created
     */
    public static function moveOnField(TeamMatch $match, array $input): array
    {
        $data = Validator::validate($input, FieldMove::rules($match->team, $match));
        $timeline = $match->timeline();
        $period = $timeline->runningPeriod() ?? $timeline->periods()->last()
            ?? throw ValidationException::withMessages(['minute' => __('kopling-sports-management::messages.no_play_yet')]);
        $offset = $timeline->length($period);

        $changes = FieldMove::fromRequest($data, $timeline->onField());
        $config = $match->sportConfig();
        if ($error = FieldMove::limitError($timeline->onField(), $changes, $match->effectiveMaxOnField($timeline), $config->keeperZone($match->effectiveFormatPreset()), $match->unavailableMemberIds($timeline))) {
            throw ValidationException::withMessages(['zone' => $error]);
        }

        return DB::transaction(function () use ($match, $timeline, $changes, $period, $offset, $data, $config) {
            $match->rememberSlots(array_map(fn (?Position $zone) => $zone ?? $config->defaultZone(), $timeline->onField()), $data);

            return collect($changes)->map(fn (?Position $zone, string $memberId) => $match->substitutions()->create([
                'period_id' => $period->id,
                'team_member_id' => $memberId,
                'direction' => $zone === null ? SubstitutionDirection::Off : SubstitutionDirection::On,
                'zone' => $zone,
                'offset_seconds' => $offset,
            ])->id)->values()->all();
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return MatchGoal|null null when the same goal was recorded moments ago
     */
    public static function recordGoal(TeamMatch $match, array $input): ?MatchGoal
    {
        $data = Validator::validate($input, [
            ...self::eventRules(),
            'opponent' => ['sometimes', 'boolean'],
            'own_goal' => ['nullable', 'exclude_if:opponent,1', 'boolean'],
            'points' => ['sometimes', 'integer', Rule::in($match->sportConfig()->pointValues())],
            'scorer_team_member_id' => ['nullable', 'prohibited_if:opponent,1', 'prohibited_if:own_goal,1', self::memberOf($match)],
            'assist_team_member_id' => ['nullable', 'prohibited_if:opponent,1', 'prohibited_if:own_goal,1', 'different:scorer_team_member_id', self::memberOf($match)],
        ]);

        [$period, $offset] = self::moment($match, $data['minute'] ?? null);

        $repeat = ($data['minute'] ?? null) === null && $match->goals()
            ->where('created_at', '>=', now()->subSeconds(self::REPEAT_SECONDS))
            ->where('opponent', (bool) ($data['opponent'] ?? false))
            ->where('own_goal', (bool) ($data['own_goal'] ?? false))
            ->where('points', (int) ($data['points'] ?? 1))
            ->where('scorer_team_member_id', $data['scorer_team_member_id'] ?? null)
            ->where('assist_team_member_id', $data['assist_team_member_id'] ?? null)
            ->exists();
        if ($repeat) {
            return null;
        }

        return $match->goals()->create([
            'period_id' => $period->id,
            'opponent' => $data['opponent'] ?? false,
            'own_goal' => $data['own_goal'] ?? false,
            'points' => $data['points'] ?? 1,
            'scorer_team_member_id' => $data['scorer_team_member_id'] ?? null,
            'assist_team_member_id' => $data['assist_team_member_id'] ?? null,
            'offset_seconds' => $offset,
        ]);
    }

    /**
     * A sanction that rules the player out takes them off the field at that moment, linked so deleting it puts them back.
     *
     * @param array<string, mixed> $input
     */
    public static function recordSanction(TeamMatch $match, array $input): MatchSanction
    {
        $config = $match->sportConfig();
        $preset = $match->effectiveFormatPreset();
        $data = Validator::validate($input, [
            ...self::eventRules(),
            'team_member_id' => ['required', 'uuid', self::memberOf($match)],
            'kind' => ['required', Rule::in(array_map(fn (SanctionKind $kind) => $kind->value, $config->sanctions()))],
        ]);

        [$period, $offset] = self::moment($match, $data['minute'] ?? null);
        $kind = SanctionKind::from($data['kind']);
        $memberId = $data['team_member_id'];
        $timeline = $match->timeline();
        $minutes = $kind->durationRule() === null ? null : $config->rule($preset, $kind->durationRule());
        $limit = $kind->limitRule() === null ? null : $config->rule($preset, $kind->limitRule());
        $count = ($timeline->sanctionCounts()[$memberId][$kind->value] ?? 0) + 1;
        $removes = $kind->shortensTeam() || ($limit !== null && $count >= $limit);

        return DB::transaction(function () use ($match, $timeline, $period, $offset, $kind, $memberId, $minutes, $removes) {
            $substitution = $removes && array_key_exists($memberId, $timeline->onFieldAt($period, $offset))
                ? $match->substitutions()->create([
                    'period_id' => $period->id,
                    'team_member_id' => $memberId,
                    'direction' => SubstitutionDirection::Off,
                    'offset_seconds' => $offset,
                ])
                : null;

            return $match->sanctions()->create([
                'period_id' => $period->id,
                'team_member_id' => $memberId,
                'kind' => $kind,
                'offset_seconds' => $offset,
                'duration_seconds' => $minutes === null ? null : $minutes * 60,
                'substitution_id' => $substitution?->id,
            ]);
        });
    }

    public static function deleteSanction(MatchSanction $sanction): void
    {
        DB::transaction(function () use ($sanction) {
            $substitution = $sanction->substitution;
            $sanction->delete();
            $substitution?->delete();
        });
    }

    /**
     * A blank minute means "now" in the running period; otherwise it's a match minute.
     *
     * @return array{0: MatchPeriod, 1: int}
     */
    private static function moment(TeamMatch $match, mixed $minute): array
    {
        $timeline = $match->timeline();

        if ($minute === null) {
            $running = $timeline->runningPeriod() ?? throw ValidationException::withMessages([
                'minute' => __('kopling-sports-management::messages.minute_required'),
            ]);

            return [$running, $timeline->length($running)];
        }

        return $timeline->momentAt((int) $minute * 60) ?? throw ValidationException::withMessages([
            'minute' => __('kopling-sports-management::messages.no_play_yet'),
        ]);
    }

    /**
     * @return array<int, Position>
     */
    private static function zones(TeamMatch $match): array
    {
        return $match->sportConfig()->zones($match->effectiveFormatPreset());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private static function eventRules(): array
    {
        return ['minute' => ['nullable', 'integer', 'min:0', 'max:240']];
    }

    private static function memberOf(TeamMatch $match): Exists
    {
        return Rule::exists('sm_team_members', 'id')->where('team_id', $match->team_id);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function createPeriod(TeamMatch $match, array $attributes): MatchPeriod
    {
        return $match->periods()->create($attributes + ['sequence' => ($match->periods()->max('sequence') ?? 0) + 1]);
    }

    private static function addStarters(TeamMatch $match, MatchPeriod $period): void
    {
        foreach ($match->lineup()->get() as $slot) {
            $match->substitutions()->create([
                'period_id' => $period->id,
                'team_member_id' => $slot->team_member_id,
                'direction' => SubstitutionDirection::On,
                'zone' => $slot->zone,
                'offset_seconds' => 0,
            ]);
        }
    }

    /**
     * Every field slot filled, or nobody left on the bench to fill them.
     */
    private static function lineupComplete(TeamMatch $match): bool
    {
        $lineup = $match->lineup()->pluck('team_member_id')->all();
        $benched = $match->team->members()->onTeamAt($match->scheduled_at)->whereNotIn('id', $lineup)->whereNotIn('id', $match->absentMemberIds())->exists();
        $needed = $match->effectiveFormatPreset()?->players_on_field ?? 1;

        return ! $benched || count($lineup) >= $needed;
    }
}
