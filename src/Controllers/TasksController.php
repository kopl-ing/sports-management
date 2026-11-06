<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\Task;
use Kopling\SportsManagement\TaskBoard;
use Kopling\SportsManagement\TaskDate;
use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskEnlistments;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\TaskFrequency;
use Kopling\SportsManagement\TaskOccurrences;
use Kopling\SportsManagement\TaskRecurrence;
use Kopling\SportsManagement\TaskOccurrence;
use Kopling\SportsManagement\TaskSchedule;
use Kopling\SportsManagement\TaskUndoWindow;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMember;

class TasksController
{
    public function index(Request $request, Team $team): View
    {
        $this->authorizeCoach($request, $team);

        $weeks = min(max($request->integer('weeks', TaskBoard::WEEKS), TaskBoard::WEEKS), 52);
        $board = new TaskBoard($team, $weeks);
        $openOnly = $request->query('filter') === 'open';
        $occurrences = $board->occurrences
            ->filter(fn (TaskOccurrence $occurrence) => ! $openOnly || $board->state($occurrence)->acceptsEnlisting())
            ->load(['events.member.person', 'events.creator']);

        return view('kopling-sports-management::tasks.index', [
            'team' => $team,
            'board' => $board,
            'wholeTasks' => $board->wholeTasks()->load(['events' => fn ($query) => $query->whereNull('occurrence_id')->with(['member.person', 'creator'])]),
            'weeks' => $occurrences->groupBy(fn (TaskOccurrence $occurrence) => $occurrence->date->startOfWeek()->toDateString()),
            'players' => TeamMember::sorted($team->members()->where('guest', false)->with('person')->get())
                ->mapWithKeys(fn (TeamMember $member) => [$member->id => $member->person->name])
                ->all(),
            'openOnly' => $openOnly,
            'laterUrl' => $board->hasLater
                ? route('kopling-sports-management::sports-management/tasks.index', ['team' => $team, 'weeks' => $weeks + TaskBoard::WEEKS] + ($openOnly ? ['filter' => 'open'] : []))
                : null,
        ]);
    }

    public function enlist(Request $request, Team $team, Task $task): RedirectResponse
    {
        $this->authorizeTask($request, $team, $task);

        $data = $request->validate([
            'team_member_id' => ['required', 'uuid'],
            'occurrence_id' => ['nullable', 'uuid'],
            'note' => ['nullable', 'string', 'max:100'],
        ]);
        $member = $team->members()->where('guest', false)->whereKey($data['team_member_id'])->first() ?? abort(404);
        $occurrence = isset($data['occurrence_id']) ? ($task->occurrences()->whereKey($data['occurrence_id'])->first() ?? abort(404)) : null;

        TaskEnlistments::enlist($task->setRelation('team', $team), $occurrence, $member, $data['note'] ?? null, $request->user());

        return back();
    }

    public function leave(Request $request, Team $team, TaskEnlistment $enlistment): RedirectResponse
    {
        $this->authorizeTask($request, $team, $enlistment->task);

        TaskEnlistments::leave($enlistment, $request->user());

        return back();
    }

    public function regenerateLink(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeCoach($request, $team);

        $team->regenerateTasksToken();

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function regenerateMemberLink(Request $request, Team $team, TeamMember $teamMember): RedirectResponse
    {
        $this->authorizeCoach($request, $team);
        abort_unless($teamMember->team_id === $team->id, 404);

        $teamMember->regenerateTasksToken();

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeCoach($request, $team);

        $this->save(new Task(['team_id' => $team->id, 'created_by' => $request->user()->id]), $team, $request);

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function update(Request $request, Team $team, Task $task): RedirectResponse
    {
        $this->authorizeTask($request, $team, $task);

        $this->save($task, $team, $request);

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function destroy(Request $request, Team $team, Task $task): RedirectResponse
    {
        $this->authorizeTask($request, $team, $task);

        $task->delete();

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function scheduleFields(Request $request, Team $team): View
    {
        $this->authorizeCoach($request, $team);

        $values = $request->query();
        if ($request->boolean('add_date')) {
            $values['dates'] = [...array_values((array) ($values['dates'] ?? [])), ['date' => '', 'time' => '']];
        }

        return view('kopling-sports-management::tasks.schedule-fields', ['team' => $team, 'values' => $values]);
    }

    private function authorizeCoach(Request $request, Team $team): void
    {
        abort_unless($team->isCoachedBy($request->user()), 403);
        abort_unless($team->tasks_enabled, 404);
    }

    private function authorizeTask(Request $request, Team $team, Task $task): void
    {
        $this->authorizeCoach($request, $team);
        abort_unless($task->team_id === $team->id, 404);
    }

    private function save(Task $task, Team $team, Request $request): void
    {
        [$attributes, $dates] = $this->validated($request);

        DB::transaction(function () use ($task, $team, $attributes, $dates) {
            $task->fill($attributes)->save();
            TaskDate::where('task_id', $task->id)->delete();
            $task->dates()->createMany($dates);
            TaskOccurrences::sync($task->setRelation('team', $team));
        });
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array{date: string, time: ?string}>}
     */
    private function validated(Request $request): array
    {
        $schedule = TaskSchedule::tryFrom((string) $request->input('schedule'));
        $frequency = TaskFrequency::tryFrom((string) $request->input('frequency'));
        $recurring = $schedule === TaskSchedule::Recurring;
        $monthly = $recurring && $frequency === TaskFrequency::Monthly;
        $byNth = $monthly && $request->input('monthly_by') === 'nth';
        $yearly = $recurring && $frequency === TaskFrequency::Yearly;
        $onDates = $schedule === TaskSchedule::Dates;
        $only = fn (bool $applies, array $rules) => $applies ? $rules : ['exclude'];

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'schedule' => ['required', Rule::enum(TaskSchedule::class)],
            'enrolment' => ['required', Rule::enum(TaskEnrolment::class)],
            'undo_window' => ['required', Rule::enum(TaskUndoWindow::class)],
            'lead_minutes' => $only($schedule?->isMatchBound() ?? false, ['nullable', 'integer', 'min:0', 'max:1440']),
            'dates' => $only($onDates, ['array', 'max:100']),
            'dates.*.date' => $only($onDates, ['nullable', 'date_format:Y-m-d']),
            'dates.*.time' => $only($onDates, ['nullable', 'date_format:H:i']),
            'frequency' => $only($recurring, ['required', Rule::enum(TaskFrequency::class)]),
            'interval' => $only($recurring && ! $yearly, ['nullable', 'integer', 'min:1', 'max:52']),
            'weekday' => $only($recurring && ($frequency === TaskFrequency::Weekly || $byNth), ['required', 'integer', 'between:1,7']),
            'monthly_by' => $only($monthly, ['required', Rule::in(['day', 'nth'])]),
            'day' => $only(($monthly && ! $byNth) || $yearly, ['required', 'integer', 'between:1,31']),
            'nth' => $only($byNth, ['required', 'integer', Rule::in([1, 2, 3, 4, TaskRecurrence::NTH_LAST])]),
            'month' => $only($yearly, ['required', 'integer', 'between:1,12']),
            'time' => $only($recurring, ['nullable', 'date_format:H:i']),
            'starts_on' => $only($recurring, ['required', 'date_format:Y-m-d']),
            'ends_on' => $only($recurring, ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on']),
            'min_people' => ['nullable', 'integer', 'min:1', 'max:99'],
            'max_people' => ['nullable', 'integer', 'min:1', 'max:99'],
            'effort_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'effort_amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'weight' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        if (isset($data['min_people'], $data['max_people']) && $data['max_people'] < $data['min_people']) {
            throw ValidationException::withMessages(['max_people' => __('kopling-sports-management::messages.tasks.max_below_min')]);
        }

        if ($recurring && CarbonImmutable::parse($data['ends_on'])->gt(CarbonImmutable::parse($data['starts_on'])->addYears(2))) {
            throw ValidationException::withMessages(['ends_on' => __('kopling-sports-management::messages.tasks.window_too_long')]);
        }

        $dates = collect($data['dates'] ?? [])
            ->filter(fn (array $row) => ($row['date'] ?? null) !== null)
            ->map(fn (array $row) => ['date' => $row['date'], 'time' => $row['time'] ?? null])
            ->unique('date')
            ->sortBy('date')
            ->values()
            ->all();

        if ($onDates && $dates === []) {
            throw ValidationException::withMessages(['dates' => __('kopling-sports-management::messages.tasks.dates_required')]);
        }

        $attributes = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'schedule' => $schedule,
            'enrolment' => $onDates && count($dates) === 1 ? TaskEnrolment::PerOccurrence : $data['enrolment'],
            'lead_minutes' => $data['lead_minutes'] ?? null,
            'recurrence' => $recurring ? TaskRecurrence::fromArray($data)->toArray() : null,
            'time' => $data['time'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'min_people' => $data['min_people'] ?? null,
            'max_people' => $data['max_people'] ?? null,
            'effort_minutes' => $data['effort_minutes'] ?? null,
            'effort_cents' => isset($data['effort_amount']) ? (int) round($data['effort_amount'] * 100) : null,
            'weight' => $data['weight'],
            'undo_window' => $data['undo_window'],
        ];

        return [$attributes, $dates];
    }
}
