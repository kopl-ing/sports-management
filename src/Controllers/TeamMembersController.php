<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Carbon\CarbonImmutable;
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
        $this->authorizeCoach($request, $team);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'jersey_number' => ['nullable', 'string', 'max:16'],
            'positions' => ['sometimes', 'array'],
            'positions.*' => [Rule::enum(Position::class)->only($team->sport->config()->zones())],
            'guest' => ['sometimes', 'boolean'],
        ], [], FieldNames::all());

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
        $this->authorizeCoach($request, $team);
        abort_unless($teamMember->team_id === $team->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'jersey_number' => ['nullable', 'string', 'max:16'],
            'positions' => ['sometimes', 'array'],
            'positions.*' => [Rule::enum(Position::class)->only($team->sport->config()->zones())],
            'guest' => ['sometimes', 'boolean'],
            'left_on' => ['nullable', 'date_format:Y-m-d'],
        ], [], FieldNames::all());

        $teamMember->person->update(['name' => $data['name']]);

        $teamMember->update([
            'jersey_number' => $data['jersey_number'] ?? null,
            'positions' => $data['positions'] ?? [],
            'guest' => $data['guest'] ?? false,
        ]);

        $leftOn = isset($data['left_on']) ? CarbonImmutable::parse($data['left_on']) : null;
        if ($leftOn === null) {
            $teamMember->update(['left_on' => null]);
        } elseif ($teamMember->left_on === null || ! $leftOn->equalTo($teamMember->left_on)) {
            $teamMember->leaveOn($leftOn, $request->user());
        }

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function destroy(Request $request, Team $team, TeamMember $teamMember): RedirectResponse
    {
        $this->authorizeCoach($request, $team);
        abort_unless($teamMember->team_id === $team->id, 404);

        $teamMember->delete();

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    private function authorizeCoach(Request $request, Team $team): void
    {
        abort_unless($team->isCoachedBy($request->user()), 403);
    }
}
