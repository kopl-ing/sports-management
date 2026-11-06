<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\TaskOccurrences;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamPeriod;

class TeamPeriodsController
{
    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeCoach($request, $team);

        $this->save(new TeamPeriod(['team_id' => $team->id]), $team, $request);

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function update(Request $request, Team $team, TeamPeriod $teamPeriod): RedirectResponse
    {
        $this->authorizeCoach($request, $team);
        abort_unless($teamPeriod->team_id === $team->id, 404);

        $this->save($teamPeriod, $team, $request);

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function destroy(Request $request, Team $team, TeamPeriod $teamPeriod): RedirectResponse
    {
        $this->authorizeCoach($request, $team);
        abort_unless($teamPeriod->team_id === $team->id, 404);

        DB::transaction(function () use ($team, $teamPeriod) {
            $teamPeriod->delete();
            TaskOccurrences::syncPeriodTasks($team);
        });

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    private function authorizeCoach(Request $request, Team $team): void
    {
        abort_unless($team->isCoachedBy($request->user()), 403);
        abort_unless($team->tasks_enabled, 404);
    }

    private function save(TeamPeriod $period, Team $team, Request $request): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ], [], FieldNames::all());

        if (CarbonImmutable::parse($data['ends_on'])->gt(CarbonImmutable::parse($data['starts_on'])->addYears(2))) {
            throw ValidationException::withMessages(['ends_on' => __('kopling-sports-management::messages.periods.too_long')]);
        }

        $overlap = $team->periods()
            ->when($period->exists, fn ($query) => $query->whereKeyNot($period->id))
            ->whereDate('starts_on', '<=', $data['ends_on'])
            ->whereDate('ends_on', '>=', $data['starts_on'])
            ->first();
        if ($overlap !== null) {
            throw ValidationException::withMessages(['starts_on' => __('kopling-sports-management::messages.periods.overlaps', ['name' => $overlap->name])]);
        }

        DB::transaction(function () use ($period, $team, $data) {
            $period->fill($data)->save();
            TaskOccurrences::syncPeriodTasks($team);
        });
    }
}
