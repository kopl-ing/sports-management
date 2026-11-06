<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum TaskFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return __('kopling-sports-management::messages.tasks.frequency.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $frequency) => [$frequency->value => $frequency->label()])->all();
    }
}
