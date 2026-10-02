<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

class Team extends Model
{
    use HasUuids;
    use SoftDeletes;

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
        return $this->belongsToMany(Person::class, 'sm_team_staff')->withPivot('owner')->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
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

    public function isOwnedBy(?Person $person): bool
    {
        return $person !== null && $this->staff()->wherePivot('owner', true)->whereKey($person->id)->exists();
    }

    protected static function booted(): void
    {
        // Per member, not left to the cascade, so each roster member's own Person goes too (see TeamMember).
        static::forceDeleting(function (self $team) {
            $team->members()->with('person')->get()->each->delete();
        });
    }
}
