<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Sport;

use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\SanctionKind;

class Basketball extends SportConfig
{
    protected function rows(): array
    {
        return [Position::Center, Position::Forward, Position::Guard];
    }

    public function defaultZone(): Position
    {
        return Position::Forward;
    }

    public function sanctions(): array
    {
        return [SanctionKind::Foul];
    }

    public function rules(): array
    {
        return ['foul_limit' => 5];
    }

    public function pointValues(): array
    {
        return [1, 2, 3];
    }
}
