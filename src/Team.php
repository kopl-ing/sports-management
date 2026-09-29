<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

class Team extends Model
{
    use HasUuids;

    protected $table = 'sm_teams';

    protected $fillable = [
        'name',
        'club',
        'season',
        'format_preset_id',
    ];

    public function formatPreset(): BelongsTo
    {
        return $this->belongsTo(TeamFormatPreset::class, 'format_preset_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'sm_team_staff')->withTimestamps();
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TeamMatch::class);
    }

    public function isStaffedBy(?Person $person): bool
    {
        return $person !== null && $this->staff()->whereKey($person->id)->exists();
    }
}
