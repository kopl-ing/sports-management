<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Kopling\Core\People\Person;

class PersonObserver
{
    /**
     * A team can only lose its last staff member this way (the last owner can't leave), and nobody could reach it after.
     */
    public function deleting(Person $person): void
    {
        Team::withTrashed()
            ->whereHas('staff', fn ($query) => $query->whereKey($person->id))
            ->withCount('staff')
            ->get()
            ->where('staff_count', 1)
            ->each->forceDelete();
    }
}
