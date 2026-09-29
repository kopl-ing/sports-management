<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\AvailabilityStatus;
use Kopling\SportsManagement\FieldMove;
use Kopling\SportsManagement\MatchGoal;
use Kopling\SportsManagement\MatchLineup;
use Kopling\SportsManagement\MatchPeriod;
use Kopling\SportsManagement\MatchSubstitution;
use Kopling\SportsManagement\MatchState;
use Kopling\SportsManagement\PeriodType;
use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\SubstitutionDirection;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMatch;
use Kopling\SportsManagement\TeamMember;

class TrackingController
{
    public const UNDO_SECONDS = 10;

    private const UNDO_GRACE_SECONDS = 60;

    public function show(Request $request, Team $team, TeamMatch $teamMatch): View
    {
        $this->authorizeMatch($request, $team, $teamMatch);

        $team->load(['formatPreset', 'members.person']);
        $teamMatch->setRelation('team', $team)->load(['formatPreset', 'availabilities', 'lineup', 'periods', 'substitutions', 'goals']);
        $timeline = $teamMatch->timeline();
        $involved = $teamMatch->lineup->pluck('team_member_id')
            ->concat($teamMatch->substitutions->pluck('team_member_id'))
            ->concat($teamMatch->goals->pluck('scorer_team_member_id'))
            ->concat($teamMatch->goals->pluck('assist_team_member_id'))
            ->filter()->unique();
        $absent = array_diff($teamMatch->availabilities->where('status', AvailabilityStatus::Absent)->pluck('team_member_id')->all(), $involved->all());
        $members = $team->members->reject(fn (TeamMember $member) => in_array($member->id, $absent, true));

        return view('kopling-sports-management::matches.track', [
            'team' => $team,
            'match' => $teamMatch,
            'timeline' => $timeline,
            'members' => $members->sortBy(fn ($member) => [$member->guest, mb_strtolower($member->person->name)])->keyBy('id'),
            'maxOnField' => $teamMatch->effectiveFormatPreset()?->players_on_field,
            'initials' => TeamMember::shortInitials($members),
            'placement' => $timeline->state() === MatchState::Planned
                ? $teamMatch->lineup->mapWithKeys(fn (MatchLineup $slot) => [$slot->team_member_id => $slot->zone])->all()
                : array_map(fn (?Position $zone) => $zone ?? Position::Midfield, $timeline->onField()),
            'availability' => $teamMatch->availabilities->pluck('status', 'team_member_id'),
        ]);
    }

    /**
     * Starting a period live ends whichever period is still running.
     */
    public function startPeriod(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);

        $data = $request->validate([
            'type' => ['required', Rule::enum(PeriodType::class)],
        ]);

        DB::transaction(function () use ($teamMatch, $data) {
            $timeline = $teamMatch->timeline();
            $first = $teamMatch->periods->isEmpty();

            if ($running = $timeline->runningPeriod()) {
                $running->update(['duration_seconds' => $timeline->length($running)]);
            }

            $period = $this->createPeriod($teamMatch, [
                'type' => $data['type'],
                'started_at' => now(),
            ]);

            if ($first) {
                $this->addStarters($teamMatch, $period);
            }
        });

        return $this->backToTracking($team, $teamMatch);
    }

    public function endPeriod(Request $request, Team $team, TeamMatch $teamMatch, MatchPeriod $period): RedirectResponse
    {
        $this->authorizePeriod($request, $team, $teamMatch, $period);

        if ($period->isRunning()) {
            $period->update(['duration_seconds' => $teamMatch->timeline()->length($period)]);
        }

        return $this->backToTracking($team, $teamMatch);
    }

    public function storePeriod(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);

        $data = $request->validate([
            'type' => ['required', Rule::enum(PeriodType::class)],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:240'],
        ]);

        DB::transaction(function () use ($teamMatch, $data) {
            $first = $teamMatch->periods()->doesntExist();
            $period = $this->createPeriod($teamMatch, [
                'type' => $data['type'],
                'duration_seconds' => $data['duration_minutes'] * 60,
            ]);

            if ($first) {
                $this->addStarters($teamMatch, $period);
            }
        });

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

    /**
     * Either side may be left empty: a starter coming on, or a player leaving without replacement.
     */
    public function storeSubstitution(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);

        $data = $request->validate([
            ...$this->eventRules(),
            'off_team_member_id' => ['nullable', 'required_without:on_team_member_id', 'different:on_team_member_id', $this->memberOf($team)],
            'on_team_member_id' => ['nullable', $this->memberOf($team)],
            'zone' => ['nullable', 'required_with:on_team_member_id', Rule::enum(Position::class)],
        ]);

        [$period, $offset] = $this->moment($teamMatch, $data['minute'] ?? null);

        $created = DB::transaction(function () use ($teamMatch, $period, $offset, $data) {
            $ids = [];
            foreach ([SubstitutionDirection::Off->value => $data['off_team_member_id'] ?? null, SubstitutionDirection::On->value => $data['on_team_member_id'] ?? null] as $direction => $memberId) {
                if ($memberId !== null) {
                    $ids[] = $teamMatch->substitutions()->create([
                        'period_id' => $period->id,
                        'team_member_id' => $memberId,
                        'direction' => $direction,
                        'zone' => $direction === SubstitutionDirection::On->value ? $data['zone'] : null,
                        'offset_seconds' => $offset,
                    ])->id;
                }
            }

            return $ids;
        });

        $this->rememberUndo($teamMatch, substitutions: $created);

        return $this->backToTracking($team, $teamMatch);
    }

    /**
     * Applies at "now" in the running period, or at the end of the last one when none is running.
     */
    public function moveOnField(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);

        $data = $request->validate(FieldMove::rules($team, $teamMatch));
        $timeline = $teamMatch->timeline();
        $period = $timeline->runningPeriod() ?? $timeline->periods()->last();
        abort_if($period === null, 409);
        $offset = $timeline->length($period);

        $changes = FieldMove::fromRequest($data, $timeline->onField());
        if ($error = FieldMove::limitError($timeline->onField(), $changes, $teamMatch->effectiveFormatPreset()?->players_on_field)) {
            throw ValidationException::withMessages(['zone' => $error]);
        }

        $created = DB::transaction(fn () => collect($changes)->map(fn (?Position $zone, string $memberId) => $teamMatch->substitutions()->create([
            'period_id' => $period->id,
            'team_member_id' => $memberId,
            'direction' => $zone === null ? SubstitutionDirection::Off : SubstitutionDirection::On,
            'zone' => $zone,
            'offset_seconds' => $offset,
        ])->id)->values()->all());

        $this->rememberUndo($teamMatch, substitutions: $created);

        return $this->backToTracking($team, $teamMatch);
    }

    public function undo(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);

        $undo = $request->session()->pull(self::undoKey($teamMatch));
        if ($undo && now()->timestamp - $undo['at'] <= self::UNDO_GRACE_SECONDS) {
            $teamMatch->goals()->whereKey($undo['goals'])->delete();
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
     */
    private function rememberUndo(TeamMatch $teamMatch, array $goals = [], array $substitutions = []): void
    {
        session()->put(self::undoKey($teamMatch), [
            'at' => now()->timestamp,
            'goals' => $goals,
            'substitutions' => $substitutions,
        ]);
    }

    public function destroySubstitution(Request $request, Team $team, TeamMatch $teamMatch, MatchSubstitution $substitution): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);
        abort_unless($substitution->match_id === $teamMatch->id, 404);

        $substitution->delete();

        return $this->backToTracking($team, $teamMatch);
    }

    public function storeGoal(Request $request, Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);

        $data = $request->validate([
            ...$this->eventRules(),
            'opponent' => ['sometimes', 'boolean'],
            'scorer_team_member_id' => ['nullable', 'prohibited_if:opponent,1', $this->memberOf($team)],
            'assist_team_member_id' => ['nullable', 'prohibited_if:opponent,1', 'different:scorer_team_member_id', $this->memberOf($team)],
        ]);

        [$period, $offset] = $this->moment($teamMatch, $data['minute'] ?? null);

        $goal = $teamMatch->goals()->create([
            'period_id' => $period->id,
            'opponent' => $data['opponent'] ?? false,
            'scorer_team_member_id' => $data['scorer_team_member_id'] ?? null,
            'assist_team_member_id' => $data['assist_team_member_id'] ?? null,
            'offset_seconds' => $offset,
        ]);

        $this->rememberUndo($teamMatch, goals: [$goal->id]);

        return $this->backToTracking($team, $teamMatch);
    }

    public function destroyGoal(Request $request, Team $team, TeamMatch $teamMatch, MatchGoal $goal): RedirectResponse
    {
        $this->authorizeMatch($request, $team, $teamMatch);
        abort_unless($goal->match_id === $teamMatch->id, 404);

        $goal->delete();

        return $this->backToTracking($team, $teamMatch);
    }

    private function createPeriod(TeamMatch $teamMatch, array $attributes): MatchPeriod
    {
        return $teamMatch->periods()->create($attributes + [
            'sequence' => ($teamMatch->periods()->max('sequence') ?? 0) + 1,
        ]);
    }

    private function addStarters(TeamMatch $teamMatch, MatchPeriod $period): void
    {
        foreach ($teamMatch->lineup()->get() as $slot) {
            $teamMatch->substitutions()->create([
                'period_id' => $period->id,
                'team_member_id' => $slot->team_member_id,
                'direction' => SubstitutionDirection::On,
                'zone' => $slot->zone,
                'offset_seconds' => 0,
            ]);
        }
    }

    /**
     * A blank minute means "now" in the running period; otherwise it's a match minute.
     *
     * @return array{0: MatchPeriod, 1: int}
     */
    private function moment(TeamMatch $teamMatch, mixed $minute): array
    {
        $timeline = $teamMatch->timeline();

        if ($minute === null) {
            $running = $timeline->runningPeriod() ?? throw ValidationException::withMessages([
                'minute' => __('kopling-sports-management::messages.minute_required'),
            ]);

            return [$running, $timeline->length($running)];
        }

        return $timeline->momentAt((int) $minute * 60) ?? throw ValidationException::withMessages([
            'minute' => __('kopling-sports-management::messages.no_play_yet'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function eventRules(): array
    {
        return [
            'minute' => ['nullable', 'integer', 'min:0', 'max:240'],
        ];
    }

    private function memberOf(Team $team): Exists
    {
        return Rule::exists('sm_team_members', 'id')->where('team_id', $team->id);
    }

    private function authorizeMatch(Request $request, Team $team, TeamMatch $teamMatch): void
    {
        abort_unless($team->isStaffedBy($request->user()), 403);
        abort_unless($teamMatch->team_id === $team->id, 404);
    }

    private function authorizePeriod(Request $request, Team $team, TeamMatch $teamMatch, MatchPeriod $period): void
    {
        $this->authorizeMatch($request, $team, $teamMatch);
        abort_unless($period->match_id === $teamMatch->id, 404);
    }

    private function backToTracking(Team $team, TeamMatch $teamMatch): RedirectResponse
    {
        return redirect()->route('kopling-sports-management::sports-management/matches.track', [$team, $teamMatch]);
    }
}
