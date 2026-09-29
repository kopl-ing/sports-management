<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum AvailabilityStatus: string
{
    case Available = 'available';
    case Maybe = 'maybe';
    case Absent = 'absent';

    public function label(): string
    {
        return __('kopling-sports-management::messages.availability_status.'.$this->value);
    }
}
