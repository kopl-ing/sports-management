<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

/**
 * A roster entry. One-to-one satellite on a `Person` (no login attached), same shape as
 * `activitypub_actors` -- see .docs/planning/sports-management-extension-plan.md, "The roster
 * pattern". `guest` marks a member borrowed from another team for a match; still a regular
 * roster row, reusable if the same guest plays again.
 */
class TeamMember extends Model
{
    use HasUuids;

    protected $table = 'sm_team_members';

    protected $fillable = [
        'team_id',
        'person_id',
        'jersey_number',
        'positions',
        'guest',
    ];

    protected function casts(): array
    {
        return [
            'positions' => AsEnumCollection::of(Position::class),
            'guest' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Whether this member's Person is only a roster record: no login, so nothing outside this roster refers to it.
     */
    public function holdsPersonOnly(): bool
    {
        $person = $this->person;

        return $person !== null
            && $person->email === null
            && $person->password === null
            && ! $person->identities()->exists();
    }

    public static function findByTasksToken(string $token): ?self
    {
        return static::where('tasks_token', $token)
            ->where('guest', false)
            ->whereHas('team', fn ($query) => $query->where('tasks_enabled', true)->where('tasks_link_mode', TaskLinkMode::PerPlayer))
            ->first();
    }

    public function tasksUrl(): ?string
    {
        return $this->tasks_token === null ? null : route('kopling-sports-management::live/tasks.player', $this->tasks_token);
    }

    public function regenerateTasksToken(): void
    {
        $this->forceFill(['tasks_token' => Str::random(40)])->save();
    }

    /** First name and last initial ("Daan K."): enough for parents to recognise their own child. */
    public function shortName(): string
    {
        $parts = preg_split('/\s+/u', trim($this->person->name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];

        return count($parts) > 1 ? $parts[0].' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : $parts[0];
    }

    protected static function booted(): void
    {
        static::creating(function (self $member) {
            $member->tasks_token ??= Str::random(40);
        });

        static::deleted(function (self $member) {
            if ($member->holdsPersonOnly()) {
                $member->person->delete();
            }
        });

        static::saved(function (self $member) {
            if ($member->guest && ($member->wasRecentlyCreated || $member->wasChanged('guest'))) {
                TeamMatch::where('team_id', $member->team_id)->doesntHave('periods')->get()
                    ->each(fn (TeamMatch $match) => $match->availabilities()->firstOrCreate(
                        ['team_member_id' => $member->id],
                        ['status' => AvailabilityStatus::Absent],
                    ));
            }
        });
    }

    /** Permanent players before guests, each by name. */
    public static function sorted(Collection $members): Collection
    {
        return $members->sort(fn (TeamMember $a, TeamMember $b) => $a->guest <=> $b->guest
            ?: strnatcasecmp($a->person->name, $b->person->name))->values();
    }

    /**
     * One letter per player; first + last initial when another player shares the first letter.
     *
     * @param iterable<TeamMember> $members
     * @return array<string, string> keyed by team member id
     */
    public static function shortInitials(iterable $members): array
    {
        $words = [];
        foreach ($members as $member) {
            $words[$member->id] = preg_split('/\s+/u', trim($member->person->name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        }

        $first = fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1));
        $counts = array_count_values(array_map(fn (array $parts) => $first($parts[0]), $words));

        return array_map(function (array $parts) use ($first, $counts) {
            $initial = $first($parts[0]);
            if ($counts[$initial] < 2) {
                return $initial;
            }

            return count($parts) > 1
                ? $initial.$first(end($parts))
                : $initial.mb_strtolower(mb_substr($parts[0], 1, 1));
        }, $words);
    }
}
