<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Support\Collection;

enum MatchState: string
{
    case Planned = 'planned';
    case Live = 'live';
    case Ended = 'ended';

    /**
     * @param Collection<int, MatchPeriod> $periods
     */
    public static function fromPeriods(Collection $periods): self
    {
        return match (true) {
            $periods->isEmpty() => self::Planned,
            $periods->contains(fn (MatchPeriod $period) => $period->isRunning()) => self::Live,
            default => self::Ended,
        };
    }

    public function label(): string
    {
        return __('kopling-sports-management::messages.match_state.'.$this->value);
    }
}
