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

    protected $description = 'Create or update the standard KNVB age-category format presets (JO7 through senioren)';

    public function handle(): int
    {
        $presets = [
            'JO7' => [4, 12],
            'JO8' => [6, 40],
            'JO9' => [6, 40],
            'JO10' => [6, 50],
            'JO11' => [8, 60],
            'JO12' => [8, 60],
            'JO13' => [11, 60],
            'JO14' => [11, 70],
            'JO15' => [11, 70],
            'JO16' => [11, 80],
            'JO17' => [11, 80],
            'JO19' => [11, 90],
            'Senioren' => [11, 90],
        ];

        $created = 0;
        $updated = 0;

        foreach ($presets as $name => [$playersOnField, $playMinutes]) {
            $preset = TeamFormatPreset::updateOrCreate(
                ['name' => $name],
                ['players_on_field' => $playersOnField, 'play_minutes' => $playMinutes],
            );

            if ($preset->wasRecentlyCreated) {
                $created++;
            } elseif ($preset->wasChanged()) {
                $updated++;
            }
        }

        $this->info("Seeded $created new and updated $updated existing KNVB format preset(s).");

        return self::SUCCESS;
    }
}
