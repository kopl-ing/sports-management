<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\TaskBoard;
use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskOccurrence;
use Kopling\SportsManagement\TaskReview;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class TaskPlanning extends TeamTool
{
    protected string $name = 'task-planning';

    protected string $description = 'Upcoming task dates and who does them: state (needs_people, enough, full, open), counts against min and max, regular people taken off a date, and season-long tasks. Coaches only.';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'weeks' => $schema->integer()->min(1)->max(12)->description('How many weeks ahead, default 4.'),
            'past_weeks' => $schema->integer()->min(1)->max(12)->description('Also list passed dates of this many weeks back, with each person\'s outcome (for record-outcome).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
            'past_weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $team = $this->tasksTeam($request);
        $board = new TaskBoard($team, $data['weeks'] ?? 4);
        $person = fn (TaskEnlistment $enlistment, ?TaskOccurrence $on = null) => array_filter([
            'enlistment_id' => $enlistment->id,
            'player' => $enlistment->member->person->name,
            'note' => $enlistment->note,
            'regular' => $on !== null && $enlistment->isWholeTask() ?: null,
            'assigned_by_staff' => $enlistment->created_by !== null ?: null,
        ], fn ($value) => $value !== null);

        return Response::json([
            'season_long' => $board->wholeTasks()->map(fn ($task) => [
                'task' => $task->title,
                'people' => $board->activeEnlistments($task)->map(fn ($enlistment) => $person($enlistment))->values()->all(),
                'max_people' => $task->max_people,
            ])->values()->all(),
            'dates' => $board->occurrences->map(fn (TaskOccurrence $occurrence) => array_filter([
                'id' => $occurrence->id,
                'task' => $occurrence->task->title,
                'date' => $occurrence->date->toDateString(),
                'time' => $occurrence->time === null ? null : substr($occurrence->time, 0, 5),
                'match' => $occurrence->matchLabel(),
                'state' => $board->state($occurrence)->value,
                'count' => $board->count($occurrence),
                'min_people' => $occurrence->task->min_people,
                'max_people' => $occurrence->task->max_people,
                'people' => $board->enlistmentsFor($occurrence)->map(fn ($enlistment) => $person($enlistment, $occurrence))->values()->all(),
                'off_this_date' => $board->skippedFor($occurrence)->map(fn ($enlistment) => $enlistment->member->person->name)->values()->all() ?: null,
            ], fn ($value) => $value !== null))->values()->all(),
            'more_later' => $board->hasLater,
            'past' => isset($data['past_weeks']) ? $this->past(new TaskReview($team, $data['past_weeks']), $person) : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function past(TaskReview $review, Closure $person): array
    {
        return $review->occurrences->map(fn (TaskOccurrence $occurrence) => [
            'id' => $occurrence->id,
            'task' => $occurrence->task->title,
            'date' => $occurrence->date->toDateString(),
            'match' => $occurrence->matchLabel(),
            'people' => $review->enlistments($occurrence)->map(fn (TaskEnlistment $enlistment) => $person($enlistment, $occurrence) + [
                'outcome' => $enlistment->outcomeFor($occurrence)?->value ?? 'to_confirm',
            ])->values()->all(),
        ])->values()->all();
    }
}
