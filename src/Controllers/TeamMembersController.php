<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Kopling\SportsManagement\Actions\SaveTeamMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMember;

class TeamMembersController
{
    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeCoach($request, $team);

        SaveTeamMember::handle($team, $request->all(), $request->user());

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function update(Request $request, Team $team, TeamMember $teamMember): RedirectResponse
    {
        $this->authorizeCoach($request, $team);
        abort_unless($teamMember->team_id === $team->id, 404);

        SaveTeamMember::handle($team, $request->all(), $request->user(), $teamMember);

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
