<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Kopling\Core\People\Person;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamInvitation;

class StaffController
{
    public function invite(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeStaff($request, $team);

        $email = Str::lower($request->validate(['email' => ['required', 'email', 'max:255']])['email']);

        if (! $team->staff()->whereRaw('lower(email) = ?', [$email])->exists()) {
            $team->invitations()->firstOrCreate(['email' => $email], ['invited_by' => $request->user()->id]);
        }

        return $this->toTeam($team)->with('status', __('kopling-sports-management::messages.invitation_sent', ['email' => $email]));
    }

    public function revoke(Request $request, Team $team, TeamInvitation $invitation): RedirectResponse
    {
        $this->authorizeStaff($request, $team);
        abort_unless($invitation->team_id === $team->id, 404);

        $invitation->delete();

        return $this->toTeam($team);
    }

    public function accept(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->isFor($request->user()) && $invitation->team !== null, 404);

        $invitation->team->staff()->syncWithoutDetaching([$request->user()->id]);
        $invitation->delete();

        return $this->toTeam($invitation->team);
    }

    public function decline(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->isFor($request->user()), 404);

        $invitation->delete();

        return redirect()->route('kopling-sports-management::sports-management/teams.index');
    }

    /**
     * Owners remove other staff; anyone may remove themselves. Owners are never removed by someone else.
     */
    public function remove(Request $request, Team $team, Person $person): RedirectResponse
    {
        $this->authorizeStaff($request, $team);
        abort_unless($team->isStaffedBy($person), 404);

        $leaving = $request->user()->is($person);
        $isOwner = $team->isOwnedBy($person);

        if (! $leaving) {
            abort_unless($request->user()->can('kopling-sports-management::manage-teams') && $team->isOwnedBy($request->user()), 403);
            abort_if($isOwner, 403);
        } elseif ($isOwner && $team->staff()->wherePivot('owner', true)->count() <= 1) {
            return back()->withErrors(['staff' => __('kopling-sports-management::messages.cannot_leave_last_owner')]);
        }

        $team->staff()->detach($person);

        return $leaving
            ? redirect()->route('kopling-sports-management::sports-management/teams.index')
            : $this->toTeam($team);
    }

    public function makeOwner(Request $request, Team $team, Person $person): RedirectResponse
    {
        abort_unless($team->isOwnedBy($request->user()), 403);
        abort_unless($team->isStaffedBy($person), 404);

        $team->staff()->updateExistingPivot($person->id, ['owner' => true]);

        return $this->toTeam($team);
    }

    private function authorizeStaff(Request $request, Team $team): void
    {
        abort_unless($team->isStaffedBy($request->user()), 403);
    }

    private function toTeam(Team $team): RedirectResponse
    {
        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }
}
