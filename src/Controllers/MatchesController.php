<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Kopling\SportsManagement\Actions\SaveMatch;
use Kopling\SportsManagement\Actions\SetAvailability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\FieldMove;
use Kopling\SportsManagement\MatchLineup;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMatch;
use Kopling\SportsManagement\TeamMember;

class MatchesController
{
    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeCoach($request, $team);

        $match = SaveMatch::handle($team, $request->all());

        return redirect()->route('kopling-sports-management::sports-management/matches.show', [$team, $match]);
    }

    public function show(Request $request, Team $team, TeamMatch $teamMatch): View
    {
        abort_unless($teamMatch->team_id === $team->id, 404);
        $teamMatch->setRelation('team', $team);
        abort_unless($teamMatch->isVisibleTo($request->user()), 403);

        $team->load(['formatPreset', 'members' => fn ($query) => $query->onTeamAt($teamMatch->scheduled_at)->with('person'), 'staff', 'periods']);
        $teamMatch->setRelation('team', $team)->load(['formatPreset', 'availabilities']);

        return view('kopling-sports-management::matches.show', [
            'team' => $team,
            'match' => $teamMatch,
            'timeline' => $teamMatch->timeline(),
            'members' => TeamMember::sorted($team->members),
            'availability' => $teamMatch->availabilities->pluck('status', 'team_member_id'),
            'isCoach' => $team->isCoachedBy($request->user()),
        ]);
    }

    public function update(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);

        SaveMatch::handle($team, $request->all(), $teamMatch);

        return redirect()->route('kopling-sports-management::sports-management/matches.show', [$team, $teamMatch]);
    }

    public function destroy(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);

        $teamMatch->delete();

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    /**
     * Full-state submit: a roster member missing from the payload (or sent empty) has its status cleared.
     */
    public function updateAvailability(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);

        SetAvailability::handle($teamMatch->setRelation('team', $team), (array) $request->input('availability', []), replace: true);

        return redirect()->route('kopling-sports-management::sports-management/matches.show', [$team, $teamMatch]);
    }

    public function moveInLineup(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);
        abort_if($teamMatch->periods()->exists(), 409);

        $data = $request->validate(FieldMove::rules($team, $teamMatch));
        $placement = $teamMatch->lineup()->get()->mapWithKeys(fn (MatchLineup $slot) => [$slot->team_member_id => $slot->zone])->all();

        $changes = FieldMove::fromRequest($data, $placement);
        if ($error = FieldMove::limitError($placement, $changes, $teamMatch->effectivePlayersOnField(), $teamMatch->sportConfig()->keeperZone($teamMatch->effectiveFormatPreset()))) {
            throw ValidationException::withMessages(['zone' => $error]);
        }

        DB::transaction(function () use ($teamMatch, $changes, $placement, $data) {
            $teamMatch->rememberSlots($placement, $data);
            foreach ($changes as $memberId => $zone) {
                $zone === null
                    ? $teamMatch->lineup()->where('team_member_id', $memberId)->delete()
                    : $teamMatch->lineup()->updateOrCreate(['team_member_id' => $memberId], ['zone' => $zone]);
            }
        });

        return redirect()->route('kopling-sports-management::sports-management/matches.track', [$team, $teamMatch]);
    }

    private function authorizeCoach(Request $request, Team $team): void
    {
        abort_unless($team->isCoachedBy($request->user()), 403);
    }

    private function authorizeMatch(Request $request, Team $team, TeamMatch $teamMatch): void
    {
        $this->authorizeCoach($request, $team);
        abort_unless($teamMatch->team_id === $team->id, 404);
    }
}
