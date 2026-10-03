<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\AvailabilityStatus;
use Kopling\SportsManagement\FieldMove;
use Kopling\SportsManagement\HomeAway;
use Kopling\SportsManagement\MatchLineup;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMatch;
use Kopling\SportsManagement\TeamMember;

class MatchesController
{
    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeStaff($request, $team);

        $match = $team->matches()->create($this->validated($request, $team));

        return redirect()->route('kopling-sports-management::sports-management/matches.show', [$team, $match]);
    }

    public function show(Request $request, Team $team, TeamMatch $teamMatch): View
    {
        $this->authorizeMatch($request, $team, $teamMatch);

        $team->load(['formatPreset', 'members.person']);
        $teamMatch->setRelation('team', $team)->load(['formatPreset', 'availabilities']);

        return view('kopling-sports-management::matches.show', [
            'team' => $team,
            'match' => $teamMatch,
            'timeline' => $teamMatch->timeline(),
            'members' => TeamMember::sorted($team->members),
            'availability' => $teamMatch->availabilities->pluck('status', 'team_member_id'),
        ]);
    }

    public function update(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);

        $teamMatch->update($this->validated($request, $team));

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

        $data = $request->validate([
            'availability' => ['sometimes', 'array'],
            'availability.*' => ['nullable', Rule::enum(AvailabilityStatus::class)],
        ]);

        $submitted = $data['availability'] ?? [];

        foreach ($team->members()->pluck('id') as $memberId) {
            $status = $submitted[$memberId] ?? null;

            if ($status === null) {
                $teamMatch->availabilities()->where('team_member_id', $memberId)->delete();

                continue;
            }

            $teamMatch->availabilities()->updateOrCreate(
                ['team_member_id' => $memberId],
                ['status' => $status],
            );
        }

        return redirect()->route('kopling-sports-management::sports-management/matches.show', [$team, $teamMatch]);
    }

    public function moveInLineup(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);
        abort_if($teamMatch->periods()->exists(), 409);

        $data = $request->validate(FieldMove::rules($team, $teamMatch));
        $placement = $teamMatch->lineup()->get()->mapWithKeys(fn (MatchLineup $slot) => [$slot->team_member_id => $slot->zone])->all();

        $changes = FieldMove::fromRequest($data, $placement);
        if ($error = FieldMove::limitError($placement, $changes, $teamMatch->effectiveFormatPreset()?->players_on_field, $teamMatch->sportConfig()->keeperZone($teamMatch->effectiveFormatPreset()))) {
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

    private function authorizeStaff(Request $request, Team $team): void
    {
        abort_unless($team->isStaffedBy($request->user()), 403);
    }

    private function authorizeMatch(Request $request, Team $team, TeamMatch $teamMatch): void
    {
        $this->authorizeStaff($request, $team);
        abort_unless($teamMatch->team_id === $team->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Team $team): array
    {
        return $request->validate([
            'opponent_name' => ['required', 'string', 'max:255'],
            'home_away' => ['required', Rule::enum(HomeAway::class)],
            'location_address' => ['nullable', 'string', 'max:1000'],
            'format_preset_id' => ['nullable', 'uuid', Rule::exists('sm_team_format_presets', 'id')->where('sport', $team->sport->value)],
            'play_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'scheduled_at' => ['required', 'date'],
        ]);
    }
}
