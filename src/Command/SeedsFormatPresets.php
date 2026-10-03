<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Command;

use Illuminate\Console\Command;
use Kopling\SportsManagement\Sport;
use Kopling\SportsManagement\TeamFormatPreset;

/**
 * Upserts one federation's presets by sport + name, so re-running applies changed values.
 */
abstract class SeedsFormatPresets extends Command
{
    abstract protected function sport(): Sport;

    /**
     * @return array<string, array{0: int, 1: int|null, 2: int|null, 3?: array<string, int|null>}> [players on field, play minutes, breaks, rules] per name
     */
    abstract protected function presets(): array;

    public function handle(): int
    {
        $created = 0;
        $updated = 0;

        foreach ($this->presets() as $name => $preset) {
            [$playersOnField, $playMinutes, $breaks] = $preset;
            $model = TeamFormatPreset::updateOrCreate(
                ['sport' => $this->sport(), 'name' => $name],
                ['players_on_field' => $playersOnField, 'play_minutes' => $playMinutes, 'breaks' => $breaks, 'rules' => $preset[3] ?? null],
            );

            if ($model->wasRecentlyCreated) {
                $created++;
            } elseif ($model->wasChanged()) {
                $updated++;
            }
        }

        $this->info("Seeded $created new and updated $updated existing {$this->sport()->label()} format preset(s).");

        return self::SUCCESS;
    }
}
