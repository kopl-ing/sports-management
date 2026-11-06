<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Per permanent player: a passed date counts for every sign-up on it whose outcome is done (see TaskEnlistment::outcomeFor()).
 */
class TaskBalance
{
    /**
     * @return Collection<int, array{
     *     member: TeamMember, left: bool, points: int, planned: int, done: int, upcoming: int, no_shows: int, pending: int, minutes: int, cents: int,
     *     items: list<array{title: string, date: ?CarbonImmutable, whole: bool, done: int, upcoming: int, no_shows: int, pending: int, points: int, note: ?string, ended_on: ?CarbonImmutable}>
     * }>
     */
    public static function for(Team $team): Collection
    {
        $members = TeamMember::sorted($team->members()->where('guest', false)->with('person')->get());
        $rows = $members->mapWithKeys(fn (TeamMember $member) => [$member->id => [
            'member' => $member, 'left' => $member->hasLeft(), 'points' => 0, 'planned' => 0, 'done' => 0, 'upcoming' => 0, 'no_shows' => 0, 'pending' => 0, 'minutes' => 0, 'cents' => 0, 'items' => [],
        ]])->all();

        $tasks = $team->tasks()
            ->with(['occurrences' => fn ($query) => $query->whereNull('cancelled_at'), 'enlistments.outcomes', 'enlistments.skips'])
            ->get()
            ->each(function (Task $task) use ($team) {
                $task->setRelation('team', $team);
                $task->enlistments->each->setRelation('task', $task);
            });

        foreach ($tasks as $task) {
            $occurrences = $task->occurrences->keyBy('id');

            foreach ($task->enlistments as $enlistment) {
                if (! isset($rows[$enlistment->team_member_id])) {
                    continue;
                }

                $covered = $enlistment->isWholeTask()
                    ? $occurrences->filter(fn (TaskOccurrence $occurrence) => $enlistment->covers($occurrence))
                    : $occurrences->only([$enlistment->occurrence_id]);
                if ($covered->isEmpty() && ! $enlistment->isWholeTask()) {
                    continue;
                }

                [$past, $future] = $covered->partition(fn (TaskOccurrence $occurrence) => $occurrence->hasPassed());
                $outcomes = $past->map(fn (TaskOccurrence $occurrence) => $enlistment->outcomeFor($occurrence));
                $done = $outcomes->filter(fn (?TaskOutcome $outcome) => $outcome === TaskOutcome::Done)->count();
                $noShows = $outcomes->filter(fn (?TaskOutcome $outcome) => $outcome === TaskOutcome::NoShow)->count();
                $pending = $outcomes->filter(fn (?TaskOutcome $outcome) => $outcome === null)->count();

                $row = &$rows[$enlistment->team_member_id];
                $row['points'] += $done * $task->weight;
                $row['done'] += $done;
                $row['upcoming'] += $future->count();
                $row['planned'] += $future->count() * $task->weight;
                $row['no_shows'] += $noShows;
                $row['pending'] += $pending;
                $row['minutes'] += $done * ($task->effort_minutes ?? 0);
                $row['cents'] += $done * ($task->effort_cents ?? 0);
                $row['items'][] = [
                    'title' => $task->title,
                    'date' => $enlistment->isWholeTask() ? null : $covered->first()->date,
                    'whole' => $enlistment->isWholeTask(),
                    'done' => $done,
                    'upcoming' => $future->count(),
                    'no_shows' => $noShows,
                    'pending' => $pending,
                    'points' => $done * $task->weight,
                    'note' => $enlistment->note,
                    'ended_on' => $enlistment->ended_on,
                ];
                unset($row);
            }
        }

        return collect($rows)
            ->map(function (array $row) {
                usort($row['items'], fn (array $a, array $b) => [$b['whole'], $a['date']?->timestamp] <=> [$a['whole'], $b['date']?->timestamp]);

                return $row;
            })
            ->sortBy(fn (array $row) => $row['points'])
            ->values();
    }
}
