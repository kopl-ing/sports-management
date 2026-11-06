<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Kopling\Core\People\Person;
use Kopling\SportsManagement\Controllers\FieldNames;
use Kopling\SportsManagement\Task;
use Kopling\SportsManagement\TaskDate;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\TaskFrequency;
use Kopling\SportsManagement\TaskOccurrences;
use Kopling\SportsManagement\TaskRecurrence;
use Kopling\SportsManagement\TaskSchedule;
use Kopling\SportsManagement\TaskUndoWindow;
use Kopling\SportsManagement\Team;

/**
 * Creates or updates a task from form-shaped input, for the task form and the MCP tools alike.
 */
class SaveTask
{
    /**
     * The task as the form would submit it, so a partial change can be merged over it.
     *
     * @return array<string, mixed>
     */
    public static function formValues(Task $task): array
    {
        $rule = $task->recurrenceRule();
        $hhmm = fn (?string $time) => $time === null ? null : substr($time, 0, 5);

        return array_filter([
            'title' => $task->title,
            'description' => $task->description,
            'schedule' => $task->schedule->value,
            'enrolment' => $task->enrolment->value,
            'undo_window' => $task->undo_window->value,
            'staff_only' => $task->staff_only,
            'confirm_each_occurrence' => $task->confirm_each_occurrence,
            'lead_minutes' => $task->lead_minutes,
            'dates' => $task->dates()->get()->map(fn ($date) => ['date' => $date->date->toDateString(), 'time' => $hhmm($date->time)])->all(),
            'frequency' => $rule?->frequency->value,
            'interval' => $rule?->interval,
            'weekday' => $rule?->weekday,
            'monthly_by' => $rule === null ? null : ($rule->nth === null ? 'day' : 'nth'),
            'day' => $rule?->day,
            'nth' => $rule?->nth,
            'month' => $rule?->month,
            'time' => $hhmm($task->time),
            'starts_on' => $task->starts_on?->toDateString(),
            'ends_on' => $task->ends_on?->toDateString(),
            'within_periods' => $task->within_periods,
            'min_people' => $task->min_people,
            'max_people' => $task->max_people,
            'effort_minutes' => $task->effort_minutes,
            'effort_amount' => $task->effort_cents === null ? null : $task->effort_cents / 100,
            'weight' => $task->weight,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function handle(Team $team, array $input, Person $by, ?Task $task = null): Task
    {
        [$attributes, $dates] = self::validated($input, $team);
        $task ??= new Task(['team_id' => $team->id, 'created_by' => $by->id]);

        DB::transaction(function () use ($task, $team, $attributes, $dates) {
            $task->fill($attributes)->save();
            TaskDate::where('task_id', $task->id)->delete();
            $task->dates()->createMany($dates);
            TaskOccurrences::sync($task->setRelation('team', $team));
        });

        return $task;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array{date: string, time: ?string}>}
     */
    private static function validated(array $input, Team $team): array
    {
        $schedule = TaskSchedule::tryFrom((string) ($input['schedule'] ?? ''));
        $frequency = TaskFrequency::tryFrom((string) ($input['frequency'] ?? ''));
        $recurring = $schedule === TaskSchedule::Recurring;
        $monthly = $recurring && $frequency === TaskFrequency::Monthly;
        $byNth = $monthly && ($input['monthly_by'] ?? null) === 'nth';
        $yearly = $recurring && $frequency === TaskFrequency::Yearly;
        $onDates = $schedule === TaskSchedule::Dates;
        $season = $schedule === TaskSchedule::Season;
        $windowed = $recurring || $season;
        $only = fn (bool $applies, array $rules) => $applies ? $rules : ['exclude'];

        $data = Validator::validate($input, [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'schedule' => ['required', Rule::enum(TaskSchedule::class)],
            'enrolment' => ['required', Rule::enum(TaskEnrolment::class)],
            'undo_window' => ['required', Rule::enum(TaskUndoWindow::class)],
            'confirm_each_occurrence' => ['sometimes', 'boolean'],
            'staff_only' => ['sometimes', 'boolean'],
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
            'starts_on' => $only($windowed, ['required', 'date_format:Y-m-d']),
            'ends_on' => $only($windowed, ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on']),
            'within_periods' => $only($windowed, ['sometimes', 'boolean']),
            'min_people' => ['nullable', 'integer', 'min:1', 'max:99'],
            'max_people' => ['nullable', 'integer', 'min:1', 'max:99'],
            'effort_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'effort_amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'weight' => ['required', 'integer', 'min:1', 'max:10'],
        ], [], FieldNames::all());

        if (isset($data['min_people'], $data['max_people']) && $data['max_people'] < $data['min_people']) {
            throw ValidationException::withMessages(['max_people' => __('kopling-sports-management::messages.tasks.max_below_min')]);
        }

        if (($data['within_periods'] ?? false) && ! $team->periods()->exists()) {
            throw ValidationException::withMessages(['within_periods' => __('kopling-sports-management::messages.periods.none_yet')]);
        }

        if ($windowed && CarbonImmutable::parse($data['ends_on'])->gt(CarbonImmutable::parse($data['starts_on'])->addYears(2))) {
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

        $enrolment = match (true) {
            $season => TaskEnrolment::WholeTask,
            $onDates && count($dates) === 1 => TaskEnrolment::PerOccurrence,
            default => TaskEnrolment::from($data['enrolment']),
        };
        $attributes = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'schedule' => $schedule,
            'enrolment' => $enrolment,
            'lead_minutes' => $data['lead_minutes'] ?? null,
            'recurrence' => $recurring ? TaskRecurrence::fromArray($data)->toArray() : null,
            'time' => $data['time'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'within_periods' => (bool) ($data['within_periods'] ?? false),
            'min_people' => $data['min_people'] ?? null,
            'max_people' => $data['max_people'] ?? null,
            'effort_minutes' => $data['effort_minutes'] ?? null,
            'effort_cents' => isset($data['effort_amount']) ? (int) round($data['effort_amount'] * 100) : null,
            'weight' => $data['weight'],
            'undo_window' => $data['undo_window'],
            'staff_only' => (bool) ($data['staff_only'] ?? false),
        ];
        $attributes['confirm_each_occurrence'] = $enrolment === TaskEnrolment::WholeTask && (bool) ($data['confirm_each_occurrence'] ?? false);

        return [$attributes, $dates];
    }
}
