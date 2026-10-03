<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Sport;

use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\SanctionKind;

class Handball extends SportConfig
{
    protected function rows(): array
    {
        return [Position::Attack, Position::Backcourt, Position::Keeper];
    }

    protected function keeper(): ?Position
    {
        return Position::Keeper;
    }

    public function defaultZone(): Position
    {
        return Position::Backcourt;
    }

    public function sanctions(): array
    {
        return [SanctionKind::Suspension, SanctionKind::RedCard];
    }

    public function rules(): array
    {
        return ['suspension_minutes' => 2, 'suspension_limit' => 3, 'red_card_short_minutes' => 2];
    }
}
