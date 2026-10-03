<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kopling\SportsManagement\Sport;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamInvitation;
use Kopling\SportsManagement\TeamMember;

class TeamsController
{
    public function index(Request $request): View
    {
        return view('kopling-sports-management::teams.index', [
            'teams' => Team::query()
                ->whereHas('staff', fn ($query) => $query->whereKey($request->user()->id))
                ->with('formatPreset')
                ->orderBy('name')
                ->get(),
            'invitations' => TeamInvitation::for($request->user())->with(['team', 'inviter'])->get(),
        ]);
    }

    public function sportFields(Request $request): View
    {
        return view('kopling-sports-management::teams.sport-fields', [
            'sport' => Sport::tryFrom((string) $request->query('sport')) ?? Sport::Football,
            'presetId' => (string) $request->query('format_preset_id'),
            'sportEditable' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $team = Team::create($this->validated($request, null));
        $team->staff()->attach($request->user(), ['owner' => true]);

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function show(Request $request, Team $team): View
    {
        $this->authorizeStaff($request, $team);

        $team->load(['formatPreset', 'staff', 'members.person']);
        $team->setRelation('members', TeamMember::sorted($team->members));

        return view('kopling-sports-management::teams.show', [
            'team' => $team,
            'upcomingMatches' => $team->matches()->with(['periods', 'goals'])->where('scheduled_at', '>=', now()->startOfDay())->orderBy('scheduled_at')->get(),
            'pastMatches' => $team->matches()->with(['periods', 'goals'])->where('scheduled_at', '<', now()->startOfDay())->orderByDesc('scheduled_at')->get(),
            'invitations' => $team->invitations()->orderBy('email')->get(),
            'isOwner' => $team->isOwnedBy($request->user()),
            'sportLocked' => $team->matches()->exists(),
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeStaff($request, $team);

        $team->update($this->validated($request, $team));

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        abort_unless($team->isOwnedBy($request->user()), 403);

        $team->forceDelete();

        return redirect()->route('kopling-sports-management::sports-management/teams.index');
    }

    private function authorizeStaff(Request $request, Team $team): void
    {
        abort_unless($team->isStaffedBy($request->user()), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Team $team): array
    {
        $locked = $team?->matches()->exists() ?? false;
        $sport = $locked ? $team->sport : (Sport::tryFrom((string) $request->input('sport')) ?? Sport::Football);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sport' => $locked ? ['exclude'] : ['required', Rule::enum(Sport::class)],
            'club' => ['nullable', 'string', 'max:255'],
            'season' => ['required', 'string', 'max:255'],
            'format_preset_id' => ['nullable', 'uuid', Rule::exists('sm_team_format_presets', 'id')->where('sport', $sport->value)],
        ]);
    }
}
