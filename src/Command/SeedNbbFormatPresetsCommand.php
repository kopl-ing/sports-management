<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Command;

use Kopling\SportsManagement\Sport;

/**
 * NBB formats; U8-U12 play eight periods of four minutes, U14 and up four quarters of ten, 3x3 one period without foul-outs.
 */
class SeedNbbFormatPresetsCommand extends SeedsFormatPresets
{
    protected $signature = 'kopling:sports-management:seed-nbb-presets';

    protected $description = 'Create or update the standard NBB age-category format presets (U8 through senioren, 3x3)';

    protected function sport(): Sport
    {
        return Sport::Basketball;
    }

    protected function presets(): array
    {
        return [
            'U8' => [5, 32, 7],
            'U10' => [5, 32, 7],
            'U12' => [5, 32, 7],
            'U14' => [5, 40, 3],
            'U16' => [5, 40, 3],
            'U18' => [5, 40, 3],
            'Senioren' => [5, 40, 3],
            '3x3' => [3, 10, 0, ['foul_limit' => null]],
        ];
    }
}
