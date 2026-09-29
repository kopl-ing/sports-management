<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum MatchState: string
{
    case Planned = 'planned';
    case Live = 'live';
    case Ended = 'ended';

    public function label(): string
    {
        return __('kopling-sports-management::messages.match_state.'.$this->value);
    }
}
