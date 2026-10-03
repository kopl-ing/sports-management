<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Command;

use Kopling\SportsManagement\Sport;

/**
 * NHV court formats, two halves each; F-jeugd plays 3 + keeper, E-jeugd 5 + keeper.
 */
class SeedNhvFormatPresetsCommand extends SeedsFormatPresets
{
    protected $signature = 'kopling:sports-management:seed-nhv-presets';

    protected $description = 'Create or update the standard NHV age-category format presets (F-jeugd through senioren)';

    protected function sport(): Sport
    {
        return Sport::Handball;
    }

    protected function presets(): array
    {
        return [
            'F-jeugd' => [4, 30, 1],
            'E-jeugd' => [6, 40, 1],
            'D-jeugd' => [7, 40, 1],
            'C-jeugd' => [7, 50, 1],
            'B-jeugd' => [7, 50, 1],
            'A-jeugd' => [7, 60, 1],
            'Senioren' => [7, 60, 1],
        ];
    }
}
