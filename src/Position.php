<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum Position: string
{
    case Keeper = 'K';
    case Defender = 'D';
    case Midfield = 'M';
    case Forward = 'F';

    public function label(): string
    {
        return __('kopling-sports-management::messages.positions.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $p) => [$p->value => $p->label()])->all();
    }
}
