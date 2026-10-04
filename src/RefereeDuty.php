<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum RefereeDuty: string
{
    case Timing = 'timing';
    case Scoring = 'scoring';
    case Sanctions = 'sanctions';

    public function label(Sport $sport): string
    {
        return $this === self::Sanctions
            ? __('kopling-sports-management::messages.sanction_button.'.$sport->value)
            : __('kopling-sports-management::messages.referee_duty.'.$this->value);
    }

    /**
     * @return array<int, self>
     */
    public static function for(Sport $sport): array
    {
        return $sport->config()->sanctions() === [] ? [self::Timing, self::Scoring] : self::cases();
    }
}
