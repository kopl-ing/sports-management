<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Support\Facades\Lang;

/**
 * Field rows of every sport; a `SportConfig` picks which it uses.
 */
enum Position: string
{
    case Keeper = 'K';
    case Defender = 'D';
    case Midfield = 'M';
    case Forward = 'F';
    case Backcourt = 'B';
    case Attack = 'A';
    case Guard = 'G';
    case Center = 'C';

    public function label(?Sport $sport = null): string
    {
        $key = 'kopling-sports-management::messages.positions.'.$this->value;

        return $sport !== null && Lang::has("kopling-sports-management::messages.positions_{$sport->value}.{$this->value}")
            ? __("kopling-sports-management::messages.positions_{$sport->value}.{$this->value}")
            : __($key);
    }

    /** @return array<string, string> */
    public static function options(Sport $sport): array
    {
        return collect($sport->config()->zones())->mapWithKeys(fn (self $p) => [$p->value => $p->label($sport)])->all();
    }
}
