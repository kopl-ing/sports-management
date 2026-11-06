<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\Actions\TrackMatch;
use Kopling\SportsManagement\AvailabilityStatus;
use Kopling\SportsManagement\FieldSlots;
use Kopling\SportsManagement\MatchGoal;
use Kopling\SportsManagement\MatchLineup;
use Kopling\SportsManagement\MatchPeriod;
use Kopling\SportsManagement\MatchSanction;
use Kopling\SportsManagement\MatchSubstitution;
use Kopling\SportsManagement\MatchState;
use Kopling\SportsManagement\PeriodType;
use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\RefereeDuty;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMatch;
use Kopling\SportsManagement\TeamMember;

class TrackingController
{
    public const UNDO_SECONDS = 10;

    private const UNDO_GRACE_SECONDS = 60;

    public function show(Request $request, Team $team, TeamMatch $teamMatch): View
    {
        return view('kopling-sports-management::matches.track', $this->matchData($request, $team, $teamMatch));
    }

    public function report(Request $request, Team $team, TeamMatch $teamMatch): View
    {
        return view('kopling-sports-management::matches.report', $this->matchData($request, $team, $teamMatch));
    }

    /**
     * @return array<string, mixed>
     */
    private function matchData(Request $request, Team $team, TeamMatch $teamMatch): array
    {
        $this->authorizeView($request, $team, $teamMatch);

        $team->load(['formatPreset', 'members' => fn ($query) => $query->onTeamAt($teamMatch->scheduled_at)->with('person')]);
        $teamMatch->setRelation('team', $team)->load(['formatPreset', 'availabilities', 'lineup', 'slots', 'periods', 'substitutions', 'goals', 'sanctions']);
        $timeline = $teamMatch->timeline();
        $involved = $teamMatch->lineup->pluck('team_member_id')
            ->concat($teamMatch->substitutions->pluck('team_member_id'))
            ->concat($teamMatch->goals->pluck('scorer_team_member_id'))
            ->concat($teamMatch->goals->pluck('assist_team_member_id'))
            ->filter()->unique();
        $absent = array_diff($teamMatch->availabilities->where('status', AvailabilityStatus::Absent)->pluck('team_member_id')->all(), $involved->all());
        $members = $team->members->reject(fn (TeamMember $member) => in_array($member->id, $absent, true));
        $placement = $timeline->state() === MatchState::Planned
            ? $teamMatch->lineup->mapWithKeys(fn (MatchLineup $slot) => [$slot->team_member_id => $slot->zone])->all()
            : array_map(fn (?Position $zone) => $zone ?? $teamMatch->sportConfig()->defaultZone(), $timeline->onField());

        return [
            'team' => $team,
            'match' => $teamMatch,
            'timeline' => $timeline,
            'members' => TeamMember::sorted($members)->keyBy('id'),
            'maxOnField' => $timeline->state() === MatchState::Planned ? $teamMatch->effectiveFormatPreset()?->players_on_field : $teamMatch->effectiveMaxOnField($timeline),
            'unavailable' => $teamMatch->unavailableMemberIds($timeline),
            'penaltyLeft' => $timeline->penaltySecondsLeft(),
            'awaitingReturn' => $timeline->awaitingReturn(),
            'sanctionKinds' => $teamMatch->sportConfig()->sanctions(),
            'pointValues' => $teamMatch->sportConfig()->pointValues(),
            'zones' => $teamMatch->sportConfig()->zones($teamMatch->effectiveFormatPreset()),
            'keeperZone' => $teamMatch->sportConfig()->keeperZone($teamMatch->effectiveFormatPreset()),
            'initials' => TeamMember::shortInitials($members),
            'placement' => $placement,
            'slots' => FieldSlots::current($placement, $teamMatch->slotRows()),
            'availability' => $teamMatch->availabilities->pluck('status', 'team_member_id'),
            'canTrack' => Gate::allows('kopling-sports-management::track-matches'),
            'isCoach' => $team->isCoachedBy($request->user()),
            'duties' => $teamMatch->dutiesOf($request->user()),
            'state' => $timeline->state(),
            'running' => $timeline->runningPeriod(),
            'played' => $timeline->playedSeconds(),
            'positionSeconds' => $timeline->positionSeconds($teamMatch->sportConfig()->defaultZone()),
            'onField' => $timeline->onField(),
        ];
    }

    public function startPeriod(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeDuty($request, $team, $teamMatch, RefereeDuty::Timing);

        TrackMatch::startPeriod($teamMatch, $request->all());

        return $this->backToTracking($team, $teamMatch);
    }

    public function endPeriod(Request $request, Team $team, TeamMatch $teamMatch, MatchPeriod $period): RedirectResponse
    {
        $this->authorizePeriod($request, $team, $teamMatch, $period);

        TrackMatch::endPeriod($teamMatch, $period);

        return $this->backToTracking($team, $teamMatch);
    }

    public function storePeriod(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeDuty($request, $team, $teamMatch, RefereeDuty::Timing);

        TrackMatch::addPeriod($teamMatch, $request->all());

        return $this->backToTracking($team, $teamMatch);
    }

    public function updatePeriod(Request $request, Team $team, TeamMatch $teamMatch, MatchPeriod $period): RedirectResponse
    {
        $this->authorizePeriod($request, $team, $teamMatch, $period);

        $data = $request->validate([
            'type' => ['required', Rule::enum(PeriodType::class)],
            'duration_minutes' => [$period->isRunning() ? 'nullable' : 'required', 'integer', 'min:0', 'max:240'],
        ]);

        $period->update([
            'type' => $data['type'],
            'duration_seconds' => isset($data['duration_minutes']) ? $data['duration_minutes'] * 60 : $period->duration_seconds,
        ]);

        return $this->backToTracking($team, $teamMatch);
    }

    public function destroyPeriod(Request $request, Team $team, TeamMatch $teamMatch, MatchPeriod $period): RedirectResponse
    {
        $this->authorizePeriod($request, $team, $teamMatch, $period);

        if ($period->substitutions()->exists() || $period->goals()->exists()) {
            throw ValidationException::withMessages([
                'period' => __('kopling-sports-management::messages.period_has_events'),
            ]);
        }

        $period->delete();

        return $this->backToTracking($team, $teamMatch);
    }

    public function storeSubstitution(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeCoach($request, $team, $teamMatch);

        $this->rememberUndo($teamMatch, substitutions: TrackMatch::substitute($teamMatch, $request->all()));

        return $this->backToTracking($team, $teamMatch);
    }

    public function moveOnField(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeCoach($request, $team, $teamMatch);

        $created = TrackMatch::moveOnField($teamMatch, $request->all());
        if ($created !== []) {
            $this->rememberUndo($teamMatch, substitutions: $created);
        }

        return $this->backToTracking($team, $teamMatch);
    }

    public function undo(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeView($request, $team, $teamMatch);

        $undo = $request->session()->pull(self::undoKey($teamMatch));
        if ($undo && now()->timestamp - $undo['at'] <= self::UNDO_GRACE_SECONDS) {
            $teamMatch->goals()->whereKey($undo['goals'])->delete();
            $teamMatch->sanctions()->whereKey($undo['sanctions'] ?? [])->delete();
            $teamMatch->substitutions()->whereKey($undo['substitutions'])->delete();
        }

        return $this->backToTracking($team, $teamMatch);
    }

    public static function undoKey(TeamMatch $teamMatch): string
    {
        return 'sm_undo.'.$teamMatch->id;
    }

    /**
     * @param array<int, string> $goals
     * @param array<int, string> $substitutions
     * @param array<int, string> $sanctions
     */
    private function rememberUndo(TeamMatch $teamMatch, array $goals = [], array $substitutions = [], array $sanctions = []): void
    {
        session()->put(self::undoKey($teamMatch), [
            'at' => now()->timestamp,
            'goals' => $goals,
            'substitutions' => $substitutions,
            'sanctions' => $sanctions,
        ]);
    }

    public function destroySubstitution(Request $request, Team $team, TeamMatch $teamMatch, MatchSubstitution $substitution): RedirectResponse
    {
        $this->authorizeCoach($request, $team, $teamMatch);
        abort_unless($substitution->match_id === $teamMatch->id, 404);

        $substitution->delete();

        return $this->backToTracking($team, $teamMatch);
    }

    public function storeGoal(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeDuty($request, $team, $teamMatch, RefereeDuty::Scoring);

        if ($goal = TrackMatch::recordGoal($teamMatch, $request->all())) {
            $this->rememberUndo($teamMatch, goals: [$goal->id]);
        }

        return $this->backToTracking($team, $teamMatch);
    }

    public function destroyGoal(Request $request, Team $team, TeamMatch $teamMatch, MatchGoal $goal): RedirectResponse
    {
        $this->authorizeDuty($request, $team, $teamMatch, RefereeDuty::Scoring);
        abort_unless($goal->match_id === $teamMatch->id, 404);

        $goal->delete();

        return $this->backToTracking($team, $teamMatch);
    }

    public function storeSanction(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeDuty($request, $team, $teamMatch, RefereeDuty::Sanctions);

        $sanction = TrackMatch::recordSanction($teamMatch, $request->all());
        $this->rememberUndo($teamMatch, substitutions: array_filter([$sanction->substitution_id]), sanctions: [$sanction->id]);

        return $this->backToTracking($team, $teamMatch);
    }

    public function destroySanction(Request $request, Team $team, TeamMatch $teamMatch, MatchSanction $sanction): RedirectResponse
    {
        $this->authorizeDuty($request, $team, $teamMatch, RefereeDuty::Sanctions);
        abort_unless($sanction->match_id === $teamMatch->id, 404);

        TrackMatch::deleteSanction($sanction);

        return $this->backToTracking($team, $teamMatch);
    }

    private function inTeam(Team $team, TeamMatch $teamMatch): void
    {
        abort_unless($teamMatch->team_id === $team->id, 404);
        $teamMatch->setRelation('team', $team);
    }

    private function authorizeView(Request $request, Team $team, TeamMatch $teamMatch): void
    {
        $this->inTeam($team, $teamMatch);
        abort_unless($teamMatch->isVisibleTo($request->user()), 403);
    }

    private function authorizeCoach(Request $request, Team $team, TeamMatch $teamMatch): void
    {
        $this->inTeam($team, $teamMatch);
        abort_unless($team->isCoachedBy($request->user()), 403);
    }

    private function authorizeDuty(Request $request, Team $team, TeamMatch $teamMatch, RefereeDuty $duty): void
    {
        $this->inTeam($team, $teamMatch);
        abort_unless($teamMatch->handles($request->user(), $duty), 403);
    }

    private function authorizePeriod(Request $request, Team $team, TeamMatch $teamMatch, MatchPeriod $period): void
    {
        $this->authorizeDuty($request, $team, $teamMatch, RefereeDuty::Timing);
        abort_unless($period->match_id === $teamMatch->id, 404);
    }

    private function backToTracking(Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $report = route('kopling-sports-management::sports-management/matches.report', [$team, $teamMatch]);

        return redirect()->to(url()->previous() === $report ? $report : route('kopling-sports-management::sports-management/matches.track', [$team, $teamMatch]));
    }
}
