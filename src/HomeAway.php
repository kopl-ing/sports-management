<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum HomeAway: string
{
    case Home = 'home';
    case Away = 'away';

    public function label(): string
    {
        return __('kopling-sports-management::messages.home_away.'.$this->value);
    }
}
