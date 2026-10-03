<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum SanctionKind: string
{
    case Foul = 'foul';
    case GreenCard = 'green_card';
    case YellowCard = 'yellow_card';
    case Suspension = 'suspension';
    case RedCard = 'red_card';

    public function label(): string
    {
        return __('kopling-sports-management::messages.sanction.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Foul => 'badge-ghost',
            self::GreenCard => 'badge-success',
            self::YellowCard => 'badge-warning',
            self::Suspension => 'badge-neutral',
            self::RedCard => 'badge-error',
        };
    }

    /**
     * The `SportConfig` rule holding how many minutes the team plays short; for a red card, null means the rest of the match.
     */
    public function durationRule(): ?string
    {
        return match ($this) {
            self::GreenCard => 'green_card_minutes',
            self::YellowCard => 'yellow_card_minutes',
            self::Suspension => 'suspension_minutes',
            self::RedCard => 'red_card_short_minutes',
            self::Foul => null,
        };
    }

    /**
     * The rule holding how many of these rule a player out for the rest of the match.
     */
    public function limitRule(): ?string
    {
        return match ($this) {
            self::Foul => 'foul_limit',
            self::Suspension => 'suspension_limit',
            default => null,
        };
    }

    public function isTimePenalty(): bool
    {
        return in_array($this, [self::GreenCard, self::YellowCard, self::Suspension], true);
    }

    public function shortensTeam(): bool
    {
        return $this !== self::Foul;
    }
}
