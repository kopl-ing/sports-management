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
     */
    public function __construct(
        private readonly Collection $periods,
        private readonly Collection $substitutions,
        private readonly Collection $goals,
        ?CarbonInterface $now = null,
    ) {
        $this->now = $now ?? now();
    }

    public function state(): MatchState
    {
        return match (true) {
            $this->periods->isEmpty() => MatchState::Planned,
            $this->runningPeriod() !== null => MatchState::Live,
            default => MatchState::Ended,
        };
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

        return __('kopling-sports-management::messages.period_play', ['number' => $number]);
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
            'us' => $this->goals->where('opponent', false)->count(),
            'them' => $this->goals->where('opponent', true)->count(),
        ];
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
     * @return array<string, Position|null> zone per team member id currently on the field
     */
    public function onField(): array
    {
        return $this->replay()['onField'];
    }

    /**
     * @return array{played: array<string, int>, onField: array<string, Position|null>}
     */
    private function replay(): array
    {
        $played = [];
        $onField = [];
        $substitutions = $this->substitutions->groupBy('period_id');

        foreach ($this->periods() as $period) {
            $length = $this->length($period);
            $cursor = 0;
            $events = ($substitutions[$period->id] ?? collect())
                ->sortBy([['offset_seconds', 'asc'], ['created_at', 'asc']]);

            foreach ($events as $substitution) {
                if ($period->type === PeriodType::Play) {
                    $at = min($substitution->offset_seconds, $length);
                    $this->credit($played, $onField, $at - $cursor);
                    $cursor = max($cursor, $at);
                }

                if ($substitution->direction === SubstitutionDirection::On) {
                    $onField[$substitution->team_member_id] = $substitution->zone;
                } else {
                    unset($onField[$substitution->team_member_id]);
                }
            }

            if ($period->type === PeriodType::Play) {
                $this->credit($played, $onField, $length - $cursor);
            }
        }

        return ['played' => $played, 'onField' => $onField];
    }

    /**
     * @param array<string, int> $played
     * @param array<string, Position|null> $onField
     */
    private function credit(array &$played, array $onField, int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        foreach (array_keys($onField) as $memberId) {
            $played[$memberId] = ($played[$memberId] ?? 0) + $seconds;
        }
    }
}
