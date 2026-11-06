<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;

enum TaskUndoWindow: string
{
    case AnyTime = 'any_time';
    case HourBefore = 'hour_before';
    case DayBefore = 'day_before';
    case Never = 'never';

    public function label(): string
    {
        return __('kopling-sports-management::messages.tasks.undo_window.'.$this->value);
    }

    public function allowsLeaving(CarbonImmutable $startsAt): bool
    {
        return match ($this) {
            self::AnyTime => true,
            self::HourBefore => now()->lt($startsAt->subHour()),
            self::DayBefore => now()->lt($startsAt->subDay()),
            self::Never => false,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $window) => [$window->value => $window->label()])->all();
    }
}
