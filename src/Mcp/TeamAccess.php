<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kopling\Core\People\Person;
use Kopling\SportsManagement\StaffRole;
use Kopling\SportsManagement\Team;

/**
 * Which teams an MCP token reaches: staff only teams that opted in; `administer-teams` every team, hidden ones included.
 */
class TeamAccess
{
    public const ADMIN = 'kopling-sports-management::administer-teams';

    public static function isAdmin(Person $person): bool
    {
        return $person->hasPermission(self::ADMIN);
    }

    /**
     * @return Collection<int, Team>
     */
    public static function teams(Person $person): Collection
    {
        return Team::query()
            ->whereHas('staff', fn ($query) => $query->whereKey($person->id))
            ->unless(self::isAdmin($person), fn ($query) => $query->where('mcp_enabled', true))
            ->with('formatPreset')
            ->orderBy('name')
            ->get();
    }

    public static function find(Person $person, mixed $id): ?Team
    {
        if (! is_string($id) || ! Str::isUuid($id)) {
            return null;
        }

        if (self::isAdmin($person)) {
            return Team::withTrashed()->find($id);
        }

        return Team::where('mcp_enabled', true)
            ->whereHas('staff', fn ($query) => $query->whereKey($person->id))
            ->find($id);
    }

    public static function coaches(Person $person, Team $team): bool
    {
        return self::isAdmin($person) || $team->isCoachedBy($person);
    }

    public static function role(Person $person, Team $team): ?StaffRole
    {
        return $team->roleOf($person);
    }
}
