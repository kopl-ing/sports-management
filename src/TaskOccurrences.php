<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TaskOccurrences
{
    /**
     * Occurrences are matched by match for match-bound tasks and by date otherwise, so a kept one keeps its id;
     * a dropped one with sign-ups is cancelled rather than deleted.
     */
    public static function sync(Task $task): void
    {
        $wanted = self::wanted($task);

        DB::transaction(function () use ($task, $wanted) {
            $existing = [];
            $stale = [];
            foreach ($task->occurrences()->get() as $occurrence) {
                $key = self::key($task, $occurrence->match_id, $occurrence->date->toDateString());
                isset($existing[$key]) ? $stale[] = $occurrence : $existing[$key] = $occurrence;
            }

            foreach ($wanted as $key => $attributes) {
                isset($existing[$key])
                    ? $existing[$key]->update($attributes + ['cancelled_at' => null])
                    : $task->occurrences()->create($attributes);
                unset($existing[$key]);
            }

            foreach ([...array_values($existing), ...$stale] as $occurrence) {
                if (! $occurrence->enlistments()->exists()) {
                    $occurrence->delete();
                } elseif ($occurrence->cancelled_at === null) {
                    $occurrence->update(['cancelled_at' => now()]);
                }
            }
        });
    }

    public static function syncMatchTasks(Team $team): void
    {
        $team->tasks()->whereIn('schedule', TaskSchedule::matchBound())->get()
            ->each(fn (Task $task) => self::sync($task->setRelation('team', $team)));
    }

    /**
     * @return array<string, array{match_id: ?string, date: string, time: ?string}>
     */
    private static function wanted(Task $task): array
    {
        $rows = match (true) {
            $task->schedule->isMatchBound() => $task->team->matches()
                ->when($task->schedule->homeAway(), fn ($query, HomeAway $side) => $query->where('home_away', $side))
                ->get()
                ->map(function (TeamMatch $match) use ($task) {
                    $at = CarbonImmutable::parse($match->scheduled_at)->subMinutes($task->lead_minutes ?? 0);

                    return ['match_id' => $match->id, 'date' => $at->toDateString(), 'time' => $at->format('H:i')];
                })->all(),
            $task->schedule === TaskSchedule::Dates => $task->dates()->get()
                ->map(fn (TaskDate $date) => ['match_id' => null, 'date' => $date->date->toDateString(), 'time' => $date->time])
                ->all(),
            default => array_map(
                fn (CarbonImmutable $date) => ['match_id' => null, 'date' => $date->toDateString(), 'time' => $task->time],
                $task->recurrenceRule()?->dates($task->starts_on, $task->ends_on) ?? [],
            ),
        };

        $wanted = [];
        foreach ($rows as $row) {
            $wanted[self::key($task, $row['match_id'], $row['date'])] = $row;
        }

        return $wanted;
    }

    private static function key(Task $task, ?string $matchId, string $date): string
    {
        return $task->schedule->isMatchBound() ? 'match:'.$matchId : 'date:'.$date;
    }
}
