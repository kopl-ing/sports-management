<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum TaskOutcome: string
{
    case Done = 'done';
    case NoShow = 'no_show';

    public function label(): string
    {
        return __('kopling-sports-management::messages.tasks.outcome.'.$this->value);
    }
}
