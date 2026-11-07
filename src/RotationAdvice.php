<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

/**
 * The next substitution moment that keeps playing time equal: keepers stay put, breaks are preferred, and nobody goes off before a full period on the field.
 */
class RotationAdvice
{
    private readonly MatchTimeline $timeline;

    private readonly ?Position $keeperZone;

    /**
     * @param array<int, string> $available team member ids that may play now
     */
    public function __construct(private readonly TeamMatch $match, private readonly array $available)
    {
        $this->timeline = $match->timeline();
        $this->keeperZone = $match->sportConfig()->keeperZone($match->effectiveFormatPreset());
    }

    /**
     * Play time each rotating player gets when the field places outside the keeper are shared equally.
     */
    public function targetSeconds(): ?int
    {
        $minutes = $this->match->effectivePlayMinutes();
        $pool = count($this->pool());
        if ($minutes === null || $pool === 0) {
            return null;
        }

        $keepers = count($this->available) - $pool;
        $seconds = 0;
        foreach ($this->timeline->fieldSizeSpans($minutes * 60) as [$span, $size]) {
            $seconds += $span * max(0, min($size, count($this->available)) - $keepers);
        }

        return $seconds === 0 ? null : intdiv($seconds, $pool * 60) * 60;
    }

    /**
     * @return array{second: int, at_break: bool, swaps: array<int, array{off: string, on: string}>}|null
     */
    public function next(): ?array
    {
        $target = $this->targetSeconds();
        $schedule = $this->schedule();
        if ($target === null || $schedule === null || $this->timeline->state() !== MatchState::Live) {
            return null;
        }

        [$breaks, $end] = $schedule;
        $now = $this->timeline->matchSeconds();
        $played = $this->timeline->playedSeconds();
        $onField = $this->timeline->onField();

        $deadlines = collect($this->pool())
            ->reject(fn (string $id) => array_key_exists($id, $onField))
            ->mapWithKeys(fn (string $id) => [$id => max($now, $end - max(0, $target - ($played[$id] ?? 0)))])
            ->filter(fn (int $deadline) => $deadline < $end)
            ->sort();
        if ($deadlines->isEmpty()) {
            return null;
        }

        $first = $deadlines->first();
        $break = collect($breaks)->filter(fn (int $second) => $second <= $first)->last();
        $at = $break ?? $first;
        $nextBreak = collect($breaks)->first(fn (int $second) => $second > $at) ?? $end;

        $on = $deadlines->filter(fn (int $deadline) => $deadline < $nextBreak)->keys();
        $off = $this->offCandidates($at, $played, $onField);

        return [
            'second' => $at,
            'at_break' => $break !== null,
            'swaps' => $on->take(count($off))->values()->map(fn (string $id, int $i) => ['off' => $off[$i], 'on' => $id])->all(),
        ];
    }

    /**
     * @param array<string, int> $played
     * @param array<string, Position|null> $onField
     * @return array<int, string> most played and longest on first; the minimum stint only when someone still meets it
     */
    private function offCandidates(int $at, array $played, array $onField): array
    {
        $moved = $this->timeline->lastMoves();
        $minimum = $this->match->playPeriodSeconds() ?? 0;
        $field = collect($this->pool())
            ->filter(fn (string $id) => array_key_exists($id, $onField))
            ->sortBy([fn (string $a, string $b) => ($played[$b] ?? 0) <=> ($played[$a] ?? 0), fn (string $a, string $b) => ($moved[$a] ?? 0) <=> ($moved[$b] ?? 0)])
            ->values();
        $rested = $field->filter(fn (string $id) => $at - ($moved[$id] ?? 0) >= $minimum)->values();

        return ($rested->isEmpty() ? $field->take(1) : $rested)->all();
    }

    /**
     * @return array<int, string> available players outside the keeper zone
     */
    private function pool(): array
    {
        $onField = $this->timeline->onField();

        return array_values(array_filter($this->available, fn (string $id) => $this->keeperZone === null || ($onField[$id] ?? null) !== $this->keeperZone));
    }

    /**
     * Match seconds of the breaks still to come (now, during a break) and of the final whistle, at the format's period length.
     *
     * @return array{0: array<int, int>, 1: int}|null
     */
    private function schedule(): ?array
    {
        $length = $this->match->playPeriodSeconds();
        $breaks = $this->match->effectiveFormatPreset()?->breaks;
        if ($length === null || $breaks === null) {
            return null;
        }

        $now = $this->timeline->matchSeconds();
        $running = $this->timeline->runningPeriod();
        $plays = $this->timeline->periods()->where('type', PeriodType::Play)->count();
        [$from, $left] = $running?->type === PeriodType::Play
            ? [$now - $this->timeline->length($running), $breaks + 2 - $plays]
            : [$now, $breaks + 1 - $plays];

        $first = $running?->type === PeriodType::Play ? 1 : 0;
        $moments = $left < $first ? [$now] : array_map(fn (int $i) => $from + $i * $length, range($first, $left));

        return [array_slice($moments, 0, -1), max($now, (int) end($moments))];
    }
}
