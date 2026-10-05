<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MatchTimeline
{
    private readonly CarbonInterface $now;

    /**
     * @param Collection<int, MatchPeriod> $periods
     * @param Collection<int, MatchSubstitution> $substitutions
     * @param Collection<int, MatchGoal> $goals
     * @param Collection<int, MatchSanction> $sanctions
     */
    public function __construct(
        private readonly Collection $periods,
        private readonly Collection $substitutions,
        private readonly Collection $goals,
        ?CarbonInterface $now = null,
        private readonly ?int $breaks = null,
        private readonly Collection $sanctions = new Collection(),
    ) {
        $this->now = $now ?? now();
    }

    public function state(): MatchState
    {
        return MatchState::fromPeriods($this->periods);
    }

    public function runningPeriod(): ?MatchPeriod
    {
        return $this->periods->first(fn (MatchPeriod $period) => $period->isRunning());
    }

    /**
     * @return Collection<int, MatchPeriod>
     */
    public function periods(): Collection
    {
        return $this->periods->sortBy('sequence')->values();
    }

    public function length(MatchPeriod $period): int
    {
        if ($period->duration_seconds !== null) {
            return $period->duration_seconds;
        }

        return $period->started_at ? max(0, (int) $period->started_at->diffInSeconds($this->now)) : 0;
    }

    /**
     * @return Collection<int, MatchPeriod>
     */
    private function playPeriods(): Collection
    {
        return $this->periods()->filter(fn (MatchPeriod $period) => $period->type === PeriodType::Play)->values();
    }

    public function label(MatchPeriod $period): string
    {
        if ($period->type === PeriodType::Break) {
            return __('kopling-sports-management::messages.period_break');
        }

        $number = $this->periods()
            ->filter(fn (MatchPeriod $other) => $other->type === PeriodType::Play && $other->sequence <= $period->sequence)
            ->count();

        $key = match (true) {
            $this->breaks === 1 && $number <= 2 => 'period_half',
            $this->breaks === 3 && $number <= 4 => 'period_quarter',
            default => 'period_play',
        };

        return __('kopling-sports-management::messages.'.$key, ['number' => $number]);
    }

    /**
     * Playing time only: the match clock stands still during breaks.
     */
    public function matchSeconds(): int
    {
        return $this->playPeriods()->sum(fn (MatchPeriod $period) => $this->length($period));
    }

    public function matchSecond(MatchPeriod $period, int $offset): int
    {
        $before = $this->playPeriods()
            ->filter(fn (MatchPeriod $other) => $other->sequence < $period->sequence)
            ->sum(fn (MatchPeriod $other) => $this->length($other));

        return $before + ($period->type === PeriodType::Play ? min($offset, $this->length($period)) : 0);
    }

    /**
     * The play period a match second falls in, and the offset within it; past the end, the last one.
     *
     * @return array{0: MatchPeriod, 1: int}|null
     */
    public function momentAt(int $matchSecond): ?array
    {
        $start = 0;
        $last = null;

        foreach ($this->playPeriods() as $period) {
            $length = $this->length($period);
            if ($matchSecond < $start + $length) {
                return [$period, $matchSecond - $start];
            }
            $last = [$period, $matchSecond - $start];
            $start += $length;
        }

        return $last;
    }

    /**
     * @return array{us: int, them: int}
     */
    public function score(): array
    {
        return [
            'us' => $this->goals->where('opponent', false)->sum(fn (MatchGoal $goal) => $goal->points ?? 1),
            'them' => $this->goals->where('opponent', true)->sum(fn (MatchGoal $goal) => $goal->points ?? 1),
        ];
    }

    /**
     * @return array<string, array{goals: int, assists: int, points: int}> keyed by team member id, most points first
     */
    public function contributions(): array
    {
        $tally = [];

        foreach ($this->goals->where('opponent', false) as $goal) {
            foreach (['goals' => $goal->scorer_team_member_id, 'assists' => $goal->assist_team_member_id] as $kind => $memberId) {
                if ($memberId !== null) {
                    $tally[$memberId] ??= ['goals' => 0, 'assists' => 0, 'points' => 0];
                    $tally[$memberId][$kind]++;
                    if ($kind === 'goals') {
                        $tally[$memberId]['points'] += $goal->points ?? 1;
                    }
                }
            }
        }

        uasort($tally, fn (array $a, array $b) => [$b['points'], $b['assists']] <=> [$a['points'], $a['assists']]);

        return $tally;
    }

    /**
     * Substitutions made during a break take effect from the start of the next play period.
     *
     * @return array<string, int> seconds played, keyed by team member id
     */
    public function playedSeconds(): array
    {
        return $this->replay()['played'];
    }

    /**
     * @return array<string, array<string, int>> seconds played per zone value, keyed by team member id; no zone counts as `$default`
     */
    public function positionSeconds(Position $default): array
    {
        $seconds = [];

        foreach ($this->replay()['positions'] as $memberId => $zones) {
            foreach ($zones as $zone => $played) {
                $zone = $zone === '' ? $default->value : $zone;
                $seconds[$memberId][$zone] = ($seconds[$memberId][$zone] ?? 0) + $played;
            }
        }

        return $seconds;
    }

    /**
     * @return array<string, Position|null> zone per team member id currently on the field
     */
    public function onField(): array
    {
        return $this->replay()['onField'];
    }

    /**
     * @return array<string, Position|null> zone per team member id on the field at that moment
     */
    public function onFieldAt(MatchPeriod $period, int $offset): array
    {
        return $this->replay($period, $offset)['onField'];
    }

    public function sanctionSecond(MatchSanction $sanction): ?int
    {
        $period = $this->periods->firstWhere('id', $sanction->period_id);

        return $period === null ? null : $this->matchSecond($period, $sanction->offset_seconds);
    }

    /**
     * @return Collection<int, MatchSanction>
     */
    public function sanctions(): Collection
    {
        return $this->sanctions;
    }

    /**
     * How many players short the team plays right now: each time penalty or red card still running counts once.
     */
    public function shortSpells(): int
    {
        $now = $this->matchSeconds();

        return $this->sanctions->filter(function (MatchSanction $sanction) use ($now) {
            $start = $this->sanctionSecond($sanction);

            return $start !== null && $sanction->kind->shortensTeam()
                && ($sanction->duration_seconds === null || $start + $sanction->duration_seconds > $now);
        })->count();
    }

    /**
     * @return array<string, int> match seconds left of a running time penalty, per team member id
     */
    public function penaltySecondsLeft(): array
    {
        $now = $this->matchSeconds();
        $left = [];

        foreach ($this->sanctions as $sanction) {
            $start = $this->sanctionSecond($sanction);
            if ($start === null || ! $sanction->kind->isTimePenalty() || $sanction->duration_seconds === null) {
                continue;
            }
            $remaining = $start + $sanction->duration_seconds - $now;
            if ($remaining > 0) {
                $left[$sanction->team_member_id] = max($left[$sanction->team_member_id] ?? 0, $remaining);
            }
        }

        return $left;
    }

    /**
     * @return array<int, string> team member ids taken off for a time penalty that has run out, not back on since
     */
    public function awaitingReturn(): array
    {
        $now = $this->matchSeconds();
        $onField = $this->onField();
        $waiting = [];

        foreach ($this->sanctions as $sanction) {
            $start = $this->sanctionSecond($sanction);
            if ($start === null || ! $sanction->kind->isTimePenalty() || $sanction->substitution_id === null
                || $start + (int) $sanction->duration_seconds > $now || array_key_exists($sanction->team_member_id, $onField)) {
                continue;
            }

            $returned = $this->substitutions->contains(fn (MatchSubstitution $substitution) => $substitution->team_member_id === $sanction->team_member_id
                && $substitution->direction === SubstitutionDirection::On
                && ($period = $this->periods->firstWhere('id', $substitution->period_id)) !== null
                && $this->matchSecond($period, $substitution->offset_seconds) >= $start);

            if (! $returned) {
                $waiting[] = $sanction->team_member_id;
            }
        }

        return array_values(array_unique($waiting));
    }

    /**
     * @return array<string, array<string, int>> sanctions per kind, per team member id
     */
    public function sanctionCounts(): array
    {
        $counts = [];
        foreach ($this->sanctions as $sanction) {
            $counts[$sanction->team_member_id][$sanction->kind->value] = ($counts[$sanction->team_member_id][$sanction->kind->value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Replays up to and including `$until` at `$untilOffset` when given, otherwise the whole match.
     *
     * @return array{played: array<string, int>, positions: array<string, array<string, int>>, onField: array<string, Position|null>}
     */
    private function replay(?MatchPeriod $until = null, ?int $untilOffset = null): array
    {
        $played = [];
        $positions = [];
        $onField = [];
        $substitutions = $this->substitutions->groupBy('period_id');

        foreach ($this->periods() as $period) {
            if ($until !== null && $period->sequence > $until->sequence) {
                break;
            }

            $length = $this->length($period);
            $cursor = 0;
            $events = ($substitutions[$period->id] ?? collect())
                ->sortBy([['offset_seconds', 'asc'], ['created_at', 'asc']]);

            foreach ($events as $substitution) {
                if ($until !== null && $period->id === $until->id && $substitution->offset_seconds > $untilOffset) {
                    break;
                }

                if ($period->type === PeriodType::Play) {
                    $at = min($substitution->offset_seconds, $length);
                    $this->credit($played, $positions, $onField, $at - $cursor);
                    $cursor = max($cursor, $at);
                }

                if ($substitution->direction === SubstitutionDirection::On) {
                    $onField[$substitution->team_member_id] = $substitution->zone;
                } else {
                    unset($onField[$substitution->team_member_id]);
                }
            }

            if ($period->type === PeriodType::Play) {
                $this->credit($played, $positions, $onField, $length - $cursor);
            }
        }

        return ['played' => $played, 'positions' => $positions, 'onField' => $onField];
    }

    /**
     * @param array<string, int> $played
     * @param array<string, array<string, int>> $positions
     * @param array<string, Position|null> $onField
     */
    private function credit(array &$played, array &$positions, array $onField, int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        foreach ($onField as $memberId => $zone) {
            $played[$memberId] = ($played[$memberId] ?? 0) + $seconds;
            $positions[$memberId][$zone?->value ?? ''] = ($positions[$memberId][$zone?->value ?? ''] ?? 0) + $seconds;
        }
    }
}
