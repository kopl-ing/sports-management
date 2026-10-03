<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Command;

use Kopling\SportsManagement\Sport;

/**
 * KNHB field formats; O8-O10 play halves without keepers, O11 and up four quarters of 17.5 minutes.
 */
class SeedKnhbFormatPresetsCommand extends SeedsFormatPresets
{
    protected $signature = 'kopling:sports-management:seed-knhb-presets';

    protected $description = 'Create or update the standard KNHB age-category format presets (O8 through senioren)';

    protected function sport(): Sport
    {
        return Sport::Hockey;
    }

    protected function presets(): array
    {
        $noKeeper = ['keeper' => 0];

        return [
            'O8 (3-tal)' => [3, 30, 1, $noKeeper],
            'O9 (6-tal)' => [6, 50, 1, $noKeeper],
            'O10 (8-tal)' => [8, 60, 1, $noKeeper],
            'O11 (9-tal)' => [9, 70, 3],
            'O12' => [11, 70, 3],
            'O14' => [11, 70, 3],
            'O16' => [11, 70, 3],
            'O18' => [11, 70, 3],
            'Senioren' => [11, 70, 3],
        ];
    }
}
