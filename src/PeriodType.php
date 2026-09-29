<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum PeriodType: string
{
    case Play = 'play';
    case Break = 'break';
}
