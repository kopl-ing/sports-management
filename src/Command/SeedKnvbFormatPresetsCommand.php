<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Command;

use Illuminate\Console\Command;
use Kopling\SportsManagement\TeamFormatPreset;

/**
 * The standard KNVB pupillen/junioren field formats. `rules_url` is left blank -- an admin
 * fills it in per preset rather than this command guessing at a KNVB URL that might be wrong.
 */
class SeedKnvbFormatPresetsCommand extends Command
{
    protected $signature = 'kopling:sports-management:seed-knvb-presets';

    protected $description = 'Create the standard KNVB age-category format presets (JO7 through senioren)';

    public function handle(): int
    {
        $presets = [
            'JO7' => 4,
            'JO8' => 4,
            'JO9' => 6,
            'JO10' => 6,
            'JO11' => 8,
            'JO12' => 8,
            'JO13' => 11,
            'JO15' => 11,
            'JO17' => 11,
            'JO19' => 11,
            'Senioren' => 11,
        ];

        $created = 0;

        foreach ($presets as $name => $playersOnField) {
            $preset = TeamFormatPreset::firstOrCreate(
                ['name' => $name],
                ['players_on_field' => $playersOnField],
            );

            if ($preset->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->info("Seeded $created new KNVB format preset(s).");

        return self::SUCCESS;
    }
}
