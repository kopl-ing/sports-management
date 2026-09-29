<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kopling\Core\People\Person;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamFormatPreset;

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
            'presets' => TeamFormatPreset::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $team = Team::create($this->validated($request));
        $team->staff()->attach($request->user());

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function show(Request $request, Team $team): View
    {
        $this->authorizeStaff($request, $team);

        $team->load(['formatPreset', 'staff', 'members.person']);
        $team->setRelation('members', $team->members->sortBy('person.name', SORT_NATURAL | SORT_FLAG_CASE)->values());

        return view('kopling-sports-management::teams.show', [
            'team' => $team,
            'upcomingMatches' => $team->matches()->with(['periods', 'goals'])->where('scheduled_at', '>=', now()->startOfDay())->orderBy('scheduled_at')->get(),
            'pastMatches' => $team->matches()->with(['periods', 'goals'])->where('scheduled_at', '<', now()->startOfDay())->orderByDesc('scheduled_at')->get(),
            'presets' => TeamFormatPreset::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeStaff($request, $team);

        $team->update($this->validated($request));

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeStaff($request, $team);

        $team->delete();

        return redirect()->route('kopling-sports-management::sports-management/teams.index');
    }

    public function addStaff(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeStaff($request, $team);

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $person = Person::where('email', $data['email'])->first();

        if (! $person) {
            return back()->withErrors(['email' => __('kopling-sports-management::messages.staff_not_found')]);
        }

        $team->staff()->syncWithoutDetaching([$person->id]);

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function removeStaff(Request $request, Team $team, Person $person): RedirectResponse
    {
        $this->authorizeStaff($request, $team);

        if ($team->staff()->count() <= 1) {
            return back()->withErrors(['staff' => __('kopling-sports-management::messages.cannot_remove_last_staff')]);
        }

        $team->staff()->detach($person);

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    private function authorizeStaff(Request $request, Team $team): void
    {
        abort_unless($team->isStaffedBy($request->user()), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'club' => ['required', 'string', 'max:255'],
            'season' => ['required', 'string', 'max:255'],
            'format_preset_id' => ['nullable', 'uuid', 'exists:sm_team_format_presets,id'],
        ]);
    }
}
