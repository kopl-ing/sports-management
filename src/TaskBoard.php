<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TaskBoard
{
    public const WEEKS = 8;

    public const SOON_DAYS = 7;

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
     * Dates in the coming days still under their task's minimum, across the given teams (those with tasks on).
     *
     * @param iterable<Team> $teams
     * @return Collection<int, array{occurrence: TaskOccurrence, count: int}>
     */
    public static function shortHanded(iterable $teams, int $days = self::SOON_DAYS): Collection
    {
        $teams = collect($teams)->filter->tasks_enabled->keyBy('id');
        if ($teams->isEmpty()) {
            return collect();
        }

        return TaskOccurrence::query()
            ->whereHas('task', fn ($query) => $query->whereIn('team_id', $teams->keys())->whereNotNull('min_people'))
            ->whereNull('cancelled_at')
            ->whereDate('date', '>=', today()->toDateString())
            ->whereDate('date', '<=', today()->addDays($days)->toDateString())
            ->with('task.wholeTaskEnlistments')
            ->withCount('enlistments')
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->map(function (TaskOccurrence $occurrence) use ($teams) {
                $task = $occurrence->task->setRelation('team', $teams[$occurrence->task->team_id]);
                $count = $occurrence->enlistments_count + $task->wholeTaskEnlistments->filter(fn (TaskEnlistment $enlistment) => $enlistment->covers($occurrence))->count();

                return ['occurrence' => $occurrence, 'count' => $count];
            })
            ->filter(fn (array $row) => $row['occurrence']->state($row['count']) === TaskOccurrenceState::NeedsPeople)
            ->values();
    }

    /**
     * Tasks tied to this match still under their minimum, or without one and taken by nobody yet.
     *
     * @return list<array{title: string, count: int, min: ?int}>
     */
    public static function vacantFor(TeamMatch $match): array
    {
        if (! $match->team->tasks_enabled) {
            return [];
        }

        return TaskOccurrence::query()
            ->where('match_id', $match->id)
            ->whereNull('cancelled_at')
            ->with('task.wholeTaskEnlistments')
            ->withCount('enlistments')
            ->get()
            ->map(function (TaskOccurrence $occurrence) {
                $task = $occurrence->task;
                $count = $occurrence->enlistments_count + $task->wholeTaskEnlistments->filter(fn (TaskEnlistment $enlistment) => $enlistment->covers($occurrence))->count();

                return ['title' => $task->title, 'count' => $count, 'min' => $task->min_people];
            })
            ->filter(fn (array $row) => $row['min'] === null ? $row['count'] === 0 : $row['count'] < $row['min'])
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
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
