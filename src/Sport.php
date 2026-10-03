<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Kopling\SportsManagement\Sport\Basketball;
use Kopling\SportsManagement\Sport\Football;
use Kopling\SportsManagement\Sport\Handball;
use Kopling\SportsManagement\Sport\Hockey;
use Kopling\SportsManagement\Sport\SportConfig;

enum Sport: string
{
    case Football = 'football';
    case Hockey = 'hockey';
    case Handball = 'handball';
    case Basketball = 'basketball';

    public function config(): SportConfig
    {
        return match ($this) {
            self::Football => new Football(),
            self::Hockey => new Hockey(),
            self::Handball => new Handball(),
            self::Basketball => new Basketball(),
        };
    }

    public function label(): string
    {
        return __('kopling-sports-management::messages.sports.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $sport) => [$sport->value => $sport->label()])->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))->all();
    }
}
