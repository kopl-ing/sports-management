<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Sport;

use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\SanctionKind;

class Hockey extends SportConfig
{
    protected function rows(): array
    {
        return [Position::Forward, Position::Midfield, Position::Defender, Position::Keeper];
    }

    protected function keeper(): ?Position
    {
        return Position::Keeper;
    }

    public function sanctions(): array
    {
        return [SanctionKind::GreenCard, SanctionKind::YellowCard, SanctionKind::RedCard];
    }

    public function rules(): array
    {
        return ['green_card_minutes' => 2, 'yellow_card_minutes' => 5, 'red_card_short_minutes' => null];
    }

    public function defaultZone(): Position
    {
        return Position::Midfield;
    }
}
