<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\Task;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListTasks extends TeamTool
{
    protected string $name = 'list-tasks';

    protected string $description = 'The volunteer tasks a team defined (driving, setting up goals, fixed roles), with when they happen, how many people they need, and their weight in the balance. Coaches only.';

    public function schema(JsonSchema $schema): array
    {
        return $this->teamSchema($schema);
    }

    public function handle(Request $request): Response
    {
        $team = $this->tasksTeam($request);

        return Response::json($team->tasks()->with('dates')->orderBy('title')->get()->map(fn (Task $task) => array_filter([
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'schedule' => $task->schedule->value,
            'when' => $task->scheduleSummary(),
            'enrolment' => $task->enrolment->value,
            'min_people' => $task->min_people,
            'max_people' => $task->max_people,
            'weight' => $task->weight,
            'effort_minutes' => $task->effort_minutes,
            'staff_only' => $task->staff_only ?: null,
        ], fn ($value) => $value !== null))->values()->all());
    }
}
