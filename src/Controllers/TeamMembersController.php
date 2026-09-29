<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kopling\Core\People\Person;
use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMember;

class TeamMembersController
{
    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeStaff($request, $team);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'jersey_number' => ['nullable', 'string', 'max:16'],
            'positions' => ['sometimes', 'array'],
            'positions.*' => [Rule::enum(Position::class)],
            'guest' => ['sometimes', 'boolean'],
        ]);

        $person = Person::create(['name' => $data['name']]);

        TeamMember::create([
            'team_id' => $team->id,
            'person_id' => $person->id,
            'jersey_number' => $data['jersey_number'] ?? null,
            'positions' => $data['positions'] ?? [],
            'guest' => $data['guest'] ?? false,
        ]);

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function update(Request $request, Team $team, TeamMember $teamMember): RedirectResponse
    {
        $this->authorizeStaff($request, $team);
        abort_unless($teamMember->team_id === $team->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'jersey_number' => ['nullable', 'string', 'max:16'],
            'positions' => ['sometimes', 'array'],
            'positions.*' => [Rule::enum(Position::class)],
            'guest' => ['sometimes', 'boolean'],
        ]);

        $teamMember->person->update(['name' => $data['name']]);

        $teamMember->update([
            'jersey_number' => $data['jersey_number'] ?? null,
            'positions' => $data['positions'] ?? [],
            'guest' => $data['guest'] ?? false,
        ]);

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function destroy(Request $request, Team $team, TeamMember $teamMember): RedirectResponse
    {
        $this->authorizeStaff($request, $team);
        abort_unless($teamMember->team_id === $team->id, 404);

        $teamMember->delete();

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    private function authorizeStaff(Request $request, Team $team): void
    {
        abort_unless($team->isStaffedBy($request->user()), 403);
    }
}
