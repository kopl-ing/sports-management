<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Kopling\Core\People\Person;

class TaskEnlistments
{
    /**
     * `$by` is the staff member enlisting on the player's behalf; null when it came through a link.
     */
    public static function enlist(Task $task, ?TaskOccurrence $occurrence, TeamMember $member, ?string $note, ?Person $by = null): TaskEnlistment
    {
        if (($occurrence === null) !== ($task->enrolment === TaskEnrolment::WholeTask)) {
            abort(404);
        }

        if ($by === null && $task->staff_only) {
            throw ValidationException::withMessages(['enlist' => __('kopling-sports-management::messages.tasks.staff_only_closed')]);
        }

        return DB::transaction(function () use ($task, $occurrence, $member, $note, $by) {
            if ($occurrence !== null) {
                $occurrence = TaskOccurrence::whereKey($occurrence->id)->lockForUpdate()->firstOrFail()->setRelation('task', $task);
                $state = $occurrence->state(self::count($occurrence, $task->wholeTaskEnlistments()->get()));
                if (! $state->acceptsEnlisting() && ! ($by !== null && $state === TaskOccurrenceState::Done)) {
                    throw ValidationException::withMessages(['enlist' => __('kopling-sports-management::messages.tasks.closed')]);
                }
            } else {
                Task::whereKey($task->id)->lockForUpdate()->first();
                $active = $task->wholeTaskEnlistments()->get()->filter->isActive()->count();
                if ($task->max_people !== null && $active >= $task->max_people) {
                    throw ValidationException::withMessages(['enlist' => __('kopling-sports-management::messages.tasks.closed')]);
                }
            }

            $enlistment = TaskEnlistment::create([
                'task_id' => $task->id,
                'occurrence_id' => $occurrence?->id,
                'team_member_id' => $member->id,
                'note' => $note,
                'created_by' => $by?->id,
            ]);
            self::log($enlistment, TaskEnlistmentAction::Enlisted, $by);

            if ($by !== null && $occurrence?->hasPassed() && $task->team->tasks_approve_completion) {
                $enlistment->outcomes()->create(['occurrence_id' => $occurrence->id, 'outcome' => TaskOutcome::Done, 'confirmed_by' => $by->id]);
            }

            return $enlistment;
        });
    }

    /**
     * Staff may always leave on a player's behalf; a link only within the task's undo window, and never once it has passed.
     */
    public static function leave(TaskEnlistment $enlistment, ?Person $by = null): void
    {
        if ($by === null && ! self::canLeave($enlistment)) {
            throw ValidationException::withMessages(['enlist' => __('kopling-sports-management::messages.tasks.undo_closed')]);
        }

        DB::transaction(function () use ($enlistment, $by) {
            self::log($enlistment, TaskEnlistmentAction::Left, $by);

            if (! $enlistment->isWholeTask()) {
                $enlistment->delete();

                return;
            }

            $next = self::nextOccurrence($enlistment->task);
            self::end($enlistment, $next === null ? CarbonImmutable::today() : $next->date->subDay());
        });
    }

    /**
     * Lets the team's current season-long sign-ups count from the period's start; returns how many moved.
     */
    public static function backfill(TeamPeriod $period): int
    {
        $start = $period->starts_on;

        $enlistments = TaskEnlistment::whereNull('occurrence_id')
            ->whereHas('task', fn ($query) => $query->where('team_id', $period->team_id))
            ->whereHas('member', fn ($query) => $query->onTeamAt($start))
            ->where(fn ($query) => $query->whereNull('ended_on')->orWhereDate('ended_on', '>=', $start->toDateString()))
            ->get()
            ->filter(fn (TaskEnlistment $enlistment) => $enlistment->startsOn()->gt($start));

        $enlistments->each(fn (TaskEnlistment $enlistment) => $enlistment->update(['starts_on' => $start]));

        return $enlistments->count();
    }

    /**
     * For a player leaving the team: sign-ups from `$on` go, season-long ones end the day before.
     */
    public static function endFor(TeamMember $member, CarbonImmutable $on, ?Person $by): void
    {
        $enlistments = TaskEnlistment::where('team_member_id', $member->id)
            ->with(['task', 'occurrence'])
            ->get()
            ->filter(fn (TaskEnlistment $enlistment) => $enlistment->isWholeTask()
                ? $enlistment->ended_on === null || $enlistment->ended_on->gte($on)
                : $enlistment->occurrence->date->gte($on));

        foreach ($enlistments as $enlistment) {
            self::log($enlistment, TaskEnlistmentAction::Left, $by);
            $enlistment->isWholeTask() ? self::end($enlistment, $on->subDay()) : $enlistment->delete();
        }
    }

    /**
     * A season-long sign-up that covered nothing yet is dropped rather than ended.
     */
    private static function end(TaskEnlistment $enlistment, CarbonImmutable $endsOn): void
    {
        $covered = $enlistment->task->occurrences()
            ->whereNull('cancelled_at')
            ->whereDate('date', '>=', $enlistment->startsOn()->toDateString())
            ->whereDate('date', '<=', $endsOn->toDateString())
            ->exists();

        $covered ? $enlistment->update(['ended_on' => $endsOn]) : $enlistment->delete();
    }

    public static function canLeave(TaskEnlistment $enlistment): bool
    {
        $task = $enlistment->task;

        if ($task->staff_only) {
            return false;
        }

        if ($enlistment->isWholeTask()) {
            if (! $enlistment->isActive()) {
                return false;
            }
            $next = self::nextOccurrence($task);

            return $next === null || $task->undo_window->allowsLeaving($next->startsAt());
        }

        $occurrence = $enlistment->occurrence;

        return $occurrence->cancelled_at === null
            && ! $occurrence->hasPassed()
            && $task->undo_window->allowsLeaving($occurrence->startsAt());
    }

    /**
     * @param Collection<int, TaskEnlistment> $wholeTask the task's whole-task enlistments
     */
    public static function count(TaskOccurrence $occurrence, Collection $wholeTask): int
    {
        $own = $occurrence->relationLoaded('enlistments') ? $occurrence->enlistments->count() : $occurrence->enlistments()->count();

        return $own + $wholeTask->filter(fn (TaskEnlistment $enlistment) => $enlistment->covers($occurrence))->count();
    }

    private static function nextOccurrence(Task $task): ?TaskOccurrence
    {
        return $task->upcomingOccurrences()->get()->first(fn (TaskOccurrence $occurrence) => ! $occurrence->hasPassed());
    }

    private static function log(TaskEnlistment $enlistment, TaskEnlistmentAction $action, ?Person $by): void
    {
        TaskEnlistmentEvent::create([
            'task_id' => $enlistment->task_id,
            'occurrence_id' => $enlistment->occurrence_id,
            'team_member_id' => $enlistment->team_member_id,
            'action' => $action,
            'note' => $enlistment->note,
            'created_by' => $by?->id,
        ]);
    }
}
