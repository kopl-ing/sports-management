<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;

/**
 * An official format for one sport (e.g. KNVB "JO11": 8 on the field, 60 minutes, one break).
 */
class TeamFormatPreset extends Model
{
    use HasUuids;

    protected $table = 'sm_team_format_presets';

    protected $attributes = [
        'sport' => 'football',
    ];

    protected $fillable = [
        'sport',
        'name',
        'players_on_field',
        'play_minutes',
        'breaks',
        'rules',
        'rules_url',
    ];

    protected function casts(): array
    {
        return [
            'players_on_field' => 'integer',
            'play_minutes' => 'integer',
            'breaks' => 'integer',
            'rules' => 'array',
            'sport' => Sport::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(Sport $sport): array
    {
        return self::where('sport', $sport)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'format_preset_id');
    }
}
