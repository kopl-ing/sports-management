<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Command;

use Kopling\SportsManagement\Sport;

/**
 * KNVB field formats; O8-O12 play halves with a time-out in each, so three breaks; JO7 is advice only.
 */
class SeedKnvbFormatPresetsCommand extends SeedsFormatPresets
{
    protected $signature = 'kopling:sports-management:seed-knvb-presets';

    protected $description = 'Create or update the standard KNVB age-category format presets (JO7 through senioren)';

    protected function sport(): Sport
    {
        return Sport::Football;
    }

    protected function presets(): array
    {
        return [
            'JO7' => [4, 12, null],
            'JO8' => [6, 40, 3],
            'JO9' => [6, 40, 3],
            'JO10' => [6, 50, 3],
            'JO11' => [8, 60, 3],
            'JO12' => [8, 60, 3],
            'JO13' => [11, 60, 1],
            'JO14' => [11, 70, 1],
            'JO15' => [11, 70, 1],
            'JO16' => [11, 80, 1],
            'JO17' => [11, 80, 1],
            'JO19' => [11, 90, 1],
            'Senioren' => [11, 90, 1],
        ];
    }
}
