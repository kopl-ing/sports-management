<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Sport;

use Kopling\SportsManagement\Position;

class Football extends SportConfig
{
    protected function rows(): array
    {
        return [Position::Forward, Position::Midfield, Position::Defender, Position::Keeper];
    }

    protected function keeper(): ?Position
    {
        return Position::Keeper;
    }

    public function defaultZone(): Position
    {
        return Position::Midfield;
    }
}
