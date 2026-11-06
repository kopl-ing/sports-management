<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kopling\Core\Database\Model;
use Illuminate\Support\Str;
use Kopling\Core\People\Person;

class Team extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'sm_teams';

    protected $attributes = [
        'sport' => 'football',
        'tasks_link_mode' => 'team',
    ];

    protected $fillable = [
        'name',
        'sport',
        'club',
        'season',
        'format_preset_id',
        'public_matches',
        'tasks_enabled',
        'tasks_link_mode',
        'tasks_balance_public',
        'tasks_approve_completion',
    ];

    protected function casts(): array
    {
        return [
            'sport' => Sport::class,
            'public_matches' => 'boolean',
            'tasks_enabled' => 'boolean',
            'tasks_link_mode' => TaskLinkMode::class,
            'tasks_balance_public' => 'boolean',
            'tasks_approve_completion' => 'boolean',
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

    public function periods(): HasMany
    {
        return $this->hasMany(TeamPeriod::class)->orderBy('starts_on');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * July to June for a season named like "2026/2027", otherwise the coming year.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function seasonBounds(): array
    {
        if (preg_match('/^(\d{4})\s*[\/-]\s*\d{2,4}$/', trim((string) $this->season), $match)) {
            $start = CarbonImmutable::create((int) $match[1], 7, 1);

            return [$start, $start->addYear()->subDay()];
        }

        $today = CarbonImmutable::today();

        return [$today, $today->addYear()->subDay()];
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

    public static function findByTasksToken(string $token): ?self
    {
        return static::where('tasks_token', $token)
            ->where('tasks_enabled', true)
            ->where('tasks_link_mode', TaskLinkMode::Team)
            ->first();
    }

    public function tasksUrl(): ?string
    {
        return $this->tasks_token === null ? null : route('kopling-sports-management::live/tasks.team', $this->tasks_token);
    }

    public function regenerateTasksToken(): void
    {
        $this->forceFill(['tasks_token' => Str::random(40)])->save();
    }

    protected static function booted(): void
    {
        static::saving(function (self $team) {
            if ($team->tasks_enabled && $team->tasks_token === null) {
                $team->tasks_token = Str::random(40);
            }
        });

        // Per member, not left to the cascade, so each roster member's own Person goes too (see TeamMember).
        static::forceDeleting(function (self $team) {
            $team->members()->with('person')->get()->each->delete();
        });
    }
}
