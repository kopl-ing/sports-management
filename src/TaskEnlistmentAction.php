<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum TaskEnlistmentAction: string
{
    case Enlisted = 'enlisted';
    case Left = 'left';
}
