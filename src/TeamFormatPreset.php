<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;

/**
 * A KNVB age-category format (e.g. "JO11", 8 players on the field). Round length/number of
 * rounds are deliberately not modeled here -- see the plan's "Format preset" decision.
 */
class TeamFormatPreset extends Model
{
    use HasUuids;

    protected $table = 'sm_team_format_presets';

    protected $fillable = [
        'name',
        'players_on_field',
        'rules_url',
    ];

    protected function casts(): array
    {
        return [
            'players_on_field' => 'integer',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'format_preset_id');
    }
}
