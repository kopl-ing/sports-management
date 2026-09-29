<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

/**
 * A roster entry. One-to-one satellite on a `Person` (no login attached), same shape as
 * `activitypub_actors` -- see .docs/planning/team-management-extension-plan.md, "The roster
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
