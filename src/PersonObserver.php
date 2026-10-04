<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Kopling\Core\People\Person;

class PersonObserver
{
    /**
     * A team can only lose its last coach this way (the last owner can't leave), and nobody could manage it after.
     */
    public function deleting(Person $person): void
    {
        $coaches = fn ($query) => $query->where('sm_team_staff.role', StaffRole::Coach->value);

        Team::withTrashed()
            ->whereHas('staff', fn ($query) => $coaches($query->whereKey($person->id)))
            ->withCount(['staff as coach_count' => $coaches])
            ->get()
            ->where('coach_count', 1)
            ->each->forceDelete();
    }
}
