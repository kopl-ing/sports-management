<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;

/**
 * Monthly runs on either `day` or `nth` + `weekday`; `nth` -1 is the last one. Weekdays are ISO (1 = Monday).
 */
class TaskRecurrence
{
    public const NTH_LAST = -1;

    public function __construct(
        public readonly TaskFrequency $frequency,
        public readonly int $interval = 1,
        public readonly ?int $weekday = null,
        public readonly ?int $day = null,
        public readonly ?int $nth = null,
        public readonly ?int $month = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $int = fn (string $key) => isset($data[$key]) ? (int) $data[$key] : null;

        return new self(
            TaskFrequency::from($data['frequency']),
            max(1, $int('interval') ?? 1),
            $int('weekday'),
            $int('day'),
            $int('nth'),
            $int('month'),
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return array_filter([
            'frequency' => $this->frequency->value,
            'interval' => $this->interval,
            'weekday' => $this->weekday,
            'day' => $this->day,
            'nth' => $this->nth,
            'month' => $this->month,
        ], fn ($value) => $value !== null);
    }

    /**
     * Intervals count from the first matching date on or after `$from`.
     *
     * @return array<int, CarbonImmutable>
     */
    public function dates(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->startOfDay();

        $dates = match ($this->frequency) {
            TaskFrequency::Weekly => $this->weekly($from, $to),
            TaskFrequency::Monthly => $this->monthly($from, $to),
            TaskFrequency::Yearly => $this->yearly($from, $to),
        };

        return array_values(array_filter($dates, fn (CarbonImmutable $date) => $date->betweenIncluded($from, $to)));
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function weekly(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $date = $from->dayOfWeekIso === $this->weekday ? $from : $from->next($this->weekday % 7);
        $dates = [];
        for (; $date <= $to; $date = $date->addWeeks($this->interval)) {
            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function monthly(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [];
        $month = $from->startOfMonth();
        $started = false;
        for (; $month <= $to; $month = $month->addMonthsNoOverflow($started ? $this->interval : 1)) {
            $date = $this->inMonth($month);
            if ($date === null || $date < $from) {
                continue;
            }
            $started = true;
            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * A day past the end of the month falls on its last day.
     */
    private function inMonth(CarbonImmutable $month): ?CarbonImmutable
    {
        if ($this->nth === null) {
            return $month->setDay(min($this->day ?? 1, $month->daysInMonth));
        }

        $date = $this->nth === self::NTH_LAST
            ? $month->lastOfMonth($this->weekday % 7)
            : $month->nthOfMonth($this->nth, $this->weekday % 7);

        return $date === false ? null : $date;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function yearly(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [];
        for ($year = $from->year; $year <= $to->year; $year++) {
            $month = CarbonImmutable::create($year, $this->month ?? 1, 1);
            $dates[] = $month->setDay(min($this->day ?? 1, $month->daysInMonth));
        }

        return $dates;
    }
}
