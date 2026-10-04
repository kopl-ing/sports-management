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

    protected $attributes = [
        'sport' => 'football',
    ];

    protected $fillable = [
        'name',
        'sport',
        'club',
        'season',
        'format_preset_id',
    ];

    protected function casts(): array
    {
        return [
            'sport' => Sport::class,
        ];
    }

    /**
     * The season a team created now plays in; it turns over in July.
     */
    public static function currentSeason(): string
    {
        $start = now()->month >= 7 ? now()->year : now()->year - 1;

        return $start.'/'.($start + 1);
    }

    /**
     * Club and season for a subtitle, skipping an empty club.
     */
    public function subtitle(): string
    {
        return implode(' · ', array_filter([$this->club, $this->season]));
    }

    public function formatPreset(): BelongsTo
    {
        return $this->belongsTo(TeamFormatPreset::class, 'format_preset_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'sm_team_staff')->withPivot('owner', 'role')->withTimestamps();
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

    public function referees(): BelongsToMany
    {
        return $this->staff()->wherePivot('role', StaffRole::Referee->value);
    }

    public function roleOf(?Person $person): ?StaffRole
    {
        $role = $person === null ? null : $this->staff()->whereKey($person->id)->first()?->pivot->role;

        return $role === null ? null : StaffRole::from($role);
    }

    public function isCoachedBy(?Person $person): bool
    {
        return $this->roleOf($person) === StaffRole::Coach;
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
