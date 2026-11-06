<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Kopling\Core\People\Person;

/**
 * Passed dates for staff to confirm, most recent first; season-long sign-ups are listed on every date they cover.
 */
class TaskReview
{
    /** @var Collection<int, TaskOccurrence> */
    public readonly Collection $occurrences;

    public readonly bool $hasEarlier;

    public function __construct(public readonly Team $team, public readonly int $weeks = TaskBoard::WEEKS, public readonly bool $pendingOnly = false)
    {
        $tasks = $team->tasks()
            ->with(['wholeTaskEnlistments.member.person', 'wholeTaskEnlistments.creator', 'wholeTaskEnlistments.outcomes'])
            ->get()
            ->each(function (Task $task) use ($team) {
                $task->setRelation('team', $team);
                $task->wholeTaskEnlistments->each->setRelation('task', $task);
            })
            ->keyBy('id');

        $from = CarbonImmutable::today()->subWeeks($weeks);
        $query = TaskOccurrence::query()
            ->whereIn('task_id', $tasks->keys())
            ->whereNull('cancelled_at')
            ->whereDate('date', '<=', CarbonImmutable::today()->toDateString());

        $this->hasEarlier = ! $pendingOnly && (clone $query)->whereDate('date', '<', $from->toDateString())->exists();
        $this->occurrences = $query
            ->when(! $pendingOnly, fn ($query) => $query->whereDate('date', '>=', $from->toDateString()))
            ->with(['enlistments.member.person', 'enlistments.creator', 'enlistments.outcomes'])
            ->orderByDesc('date')
            ->orderByDesc('time')
            ->get()
            ->each(function (TaskOccurrence $occurrence) use ($tasks) {
                $task = $tasks[$occurrence->task_id];
                $occurrence->setRelation('task', $task);
                $occurrence->enlistments->each->setRelation('task', $task);
            })
            ->filter(fn (TaskOccurrence $occurrence) => $occurrence->hasPassed() && (! $pendingOnly || $this->pending($occurrence) > 0))
            ->values();
    }

    /**
     * @return Collection<int, TaskEnlistment>
     */
    public function enlistments(TaskOccurrence $occurrence): Collection
    {
        return $occurrence->enlistments->concat(
            $occurrence->task->wholeTaskEnlistments->filter(fn (TaskEnlistment $enlistment) => $enlistment->covers($occurrence)),
        )->values();
    }

    public function pending(TaskOccurrence $occurrence): int
    {
        return $this->enlistments($occurrence)->filter(fn (TaskEnlistment $enlistment) => $enlistment->outcomeFor($occurrence) === null)->count();
    }

    /**
     * Marks every sign-up still waiting on these dates as done; returns how many.
     *
     * @param iterable<TaskOccurrence> $occurrences
     */
    public function confirmAll(iterable $occurrences, ?Person $by): int
    {
        $confirmed = 0;
        foreach ($occurrences as $occurrence) {
            foreach ($this->enlistments($occurrence) as $enlistment) {
                if ($enlistment->outcomeFor($occurrence) === null) {
                    $enlistment->outcomes()->create(['occurrence_id' => $occurrence->id, 'outcome' => TaskOutcome::Done, 'confirmed_by' => $by?->id]);
                    $confirmed++;
                }
            }
        }

        return $confirmed;
    }
}
