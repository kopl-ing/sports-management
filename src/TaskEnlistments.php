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
            $endsOn = $next === null ? CarbonImmutable::today() : $next->date->subDay();
            $covered = $enlistment->task->occurrences()
                ->whereNull('cancelled_at')
                ->whereDate('date', '>=', $enlistment->startsOn()->toDateString())
                ->whereDate('date', '<=', $endsOn->toDateString())
                ->exists();
            $covered ? $enlistment->update(['ended_on' => $endsOn]) : $enlistment->delete();
        });
    }

    public static function canLeave(TaskEnlistment $enlistment): bool
    {
        $task = $enlistment->task;

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
