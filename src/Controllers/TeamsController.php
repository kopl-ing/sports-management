<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kopling\SportsManagement\MatchState;
use Kopling\SportsManagement\Sport;
use Kopling\SportsManagement\StaffRole;
use Kopling\SportsManagement\TaskBoard;
use Kopling\SportsManagement\TaskLinkMode;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamInvitation;
use Kopling\SportsManagement\TeamMatch;
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
        $role = $team->roleOf($request->user());
        abort_if($role === null, 403);
        $isCoach = $role === StaffRole::Coach;

        $team->load(['formatPreset', 'staff', 'members.person', 'periods']);
        [$current, $former] = $team->members->partition(fn (TeamMember $member) => ! $member->hasLeft());
        $team->setRelation('members', TeamMember::sorted($current));
        [$pastMatches, $upcomingMatches] = $team->matches()
            ->with(['periods', 'goals'])
            ->when(! $isCoach, fn ($query) => $query->where('referee_person_id', $request->user()->id))
            ->orderBy('scheduled_at')
            ->get()
            ->partition(fn (TeamMatch $match) => $match->scheduled_at < now()->startOfDay() || MatchState::fromPeriods($match->periods) === MatchState::Ended);

        return view('kopling-sports-management::teams.show', [
            'team' => $team,
            'isCoach' => $isCoach,
            'formerMembers' => $former->sortByDesc(fn (TeamMember $member) => $member->left_on)->values(),
            'upcomingMatches' => $upcomingMatches->values(),
            'pastMatches' => $pastMatches->reverse()->values(),
            'invitations' => $team->invitations()->orderBy('email')->get(),
            'isOwner' => $team->isOwnedBy($request->user()),
            'sportLocked' => $team->matches()->exists(),
            'tasks' => $tasks = $isCoach && $team->tasks_enabled
                ? $team->tasks()->with(['dates', 'upcomingOccurrences', 'wholeTaskEnlistments.member.person'])->orderBy('title')->get()
                : collect(),
            'players' => TeamMember::sorted($current->where('guest', false))->mapWithKeys(fn (TeamMember $member) => [$member->id => $member->person->name])->all(),
            'shortHanded' => $isCoach ? TaskBoard::shortHanded([$team]) : collect(),
            'duplicate' => $request->filled('duplicate') ? $tasks->find($request->query('duplicate')) : null,
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        abort_unless($team->isCoachedBy($request->user()), 403);

        $team->update($this->validated($request, $team));

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        abort_unless($team->isOwnedBy($request->user()), 403);

        $team->forceDelete();

        return redirect()->route('kopling-sports-management::sports-management/teams.index');
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
            'public_matches' => ['sometimes', 'boolean'],
            'tasks_enabled' => ['sometimes', 'boolean'],
            'tasks_link_mode' => ['sometimes', Rule::enum(TaskLinkMode::class)],
            'tasks_balance_public' => ['sometimes', 'boolean'],
            'tasks_approve_completion' => ['sometimes', 'boolean'],
        ]);
    }
}
