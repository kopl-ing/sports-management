<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TaskBoard
{
    public const WEEKS = 8;

    /** @var Collection<int, Task> */
    public readonly Collection $tasks;

    /** @var Collection<int, TaskOccurrence> */
    public readonly Collection $occurrences;

    public readonly bool $hasLater;

    /**
     * Cancelled occurrences stay on the board only while someone is still signed up for them.
     */
    public function __construct(public readonly Team $team, public readonly int $weeks = self::WEEKS)
    {
        $this->tasks = $team->tasks()
            ->with(['wholeTaskEnlistments.member.person', 'wholeTaskEnlistments.creator'])
            ->withExists(['occurrences as has_upcoming' => fn ($query) => $query->whereNull('cancelled_at')->whereDate('date', '>=', today()->toDateString())])
            ->orderBy('title')
            ->get()
            ->each(fn (Task $task) => $task->setRelation('team', $team));

        $from = CarbonImmutable::today();
        $until = $from->addWeeks($weeks);
        $query = TaskOccurrence::query()
            ->whereIn('task_id', $this->tasks->where('enrolment', TaskEnrolment::PerOccurrence)->modelKeys())
            ->whereDate('date', '>=', $from->toDateString())
            ->where(fn ($query) => $query->whereNull('cancelled_at')->orWhereHas('enlistments'));

        $this->hasLater = (clone $query)->whereDate('date', '>', $until->toDateString())->exists();
        $this->occurrences = $query
            ->whereDate('date', '<=', $until->toDateString())
            ->with(['enlistments.member.person', 'enlistments.creator'])
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->each(function (TaskOccurrence $occurrence) {
                $task = $this->tasks->find($occurrence->task_id);
                $occurrence->setRelation('task', $task);
                $occurrence->enlistments->each(fn (TaskEnlistment $enlistment) => $enlistment->setRelation('task', $task)->setRelation('occurrence', $occurrence));
            });
    }

    /**
     * @return Collection<int, Task>
     */
    public function wholeTasks(): Collection
    {
        return $this->tasks->where('enrolment', TaskEnrolment::WholeTask)->filter->has_upcoming->values();
    }

    /**
     * @return Collection<int, TaskEnlistment>
     */
    public function activeEnlistments(Task $task): Collection
    {
        return $task->wholeTaskEnlistments->filter->isActive()->each->setRelation('task', $task)->values();
    }

    public function count(TaskOccurrence $occurrence): int
    {
        return TaskEnlistments::count($occurrence, $occurrence->task->wholeTaskEnlistments);
    }

    public function state(TaskOccurrence $occurrence): TaskOccurrenceState
    {
        return $occurrence->state($this->count($occurrence));
    }

    public function wholeTaskAcceptsEnlisting(Task $task): bool
    {
        return $task->max_people === null || $this->activeEnlistments($task)->count() < $task->max_people;
    }
}
