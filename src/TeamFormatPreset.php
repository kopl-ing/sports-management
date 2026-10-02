<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;

/**
 * A KNVB age-category format (e.g. "JO11", 8 players on the field, 60 minutes of play). Only the
 * total play time is modeled, never a round/break schedule -- see the plan's "Format preset" decision.
 */
class TeamFormatPreset extends Model
{
    use HasUuids;

    protected $table = 'sm_team_format_presets';

    protected $fillable = [
        'name',
        'players_on_field',
        'play_minutes',
        'rules_url',
    ];

    protected function casts(): array
    {
        return [
            'players_on_field' => 'integer',
            'play_minutes' => 'integer',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'format_preset_id');
    }
}
