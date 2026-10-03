<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Support\Facades\Lang;
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

    // Set by whatever knows the visitor's sport (e.g. a sport's landing page) to preselect it when creating a team.
    public const SESSION_KEY = 'kopling-sports-management.sport';

    public function config(): SportConfig
    {
        return match ($this) {
            self::Football => new Football(),
            self::Hockey => new Hockey(),
            self::Handball => new Handball(),
            self::Basketball => new Basketball(),
        };
    }

    /**
     * A message in this sport's own words: `by_sport.{sport}.{key}` in the current locale, else the generic `{key}`.
     *
     * @param array<string, mixed> $replace
     */
    public function trans(string $key, array $replace = []): string
    {
        $override = "kopling-sports-management::messages.by_sport.{$this->value}.{$key}";

        return __(Lang::hasForLocale($override) ? $override : "kopling-sports-management::messages.{$key}", $replace);
    }

    public function label(): string
    {
        return __('kopling-sports-management::messages.sports.'.$this->value);
    }

    public static function preferred(): self
    {
        return self::tryFrom((string) session(self::SESSION_KEY)) ?? self::Football;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $sport) => [$sport->value => $sport->label()])->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))->all();
    }
}
