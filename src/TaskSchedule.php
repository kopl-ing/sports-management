<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum TaskSchedule: string
{
    case AllMatches = 'all_matches';
    case HomeMatches = 'home_matches';
    case AwayMatches = 'away_matches';
    case Dates = 'dates';
    case Recurring = 'recurring';
    case Season = 'season';

    public function label(): string
    {
        return __('kopling-sports-management::messages.tasks.schedule.'.$this->value);
    }

    public function isMatchBound(): bool
    {
        return in_array($this, [self::AllMatches, self::HomeMatches, self::AwayMatches], true);
    }

    public function homeAway(): ?HomeAway
    {
        return match ($this) {
            self::HomeMatches => HomeAway::Home,
            self::AwayMatches => HomeAway::Away,
            default => null,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function matchBound(): array
    {
        return array_values(array_filter(self::cases(), fn (self $schedule) => $schedule->isMatchBound()));
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $schedule) => [$schedule->value => $schedule->label()])->all();
    }
}
