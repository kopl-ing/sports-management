<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Actions\SaveTask;
use Kopling\SportsManagement\Mcp\TeamTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateTask extends TeamTool
{
    protected string $name = 'update-task';

    protected string $description = 'Changes a task; only the fields given change. Dates that drop out are cancelled when someone had taken them on.';

    protected ?string $permission = 'kopling-sports-management::manage-teams';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'task_id' => $schema->string()->description('The task id, from list-tasks.')->required(),
            ...CreateTask::taskSchema($schema, required: false),
        ];
    }

    public function handle(Request $request): Response
    {
        $team = $this->tasksTeam($request);
        $task = $this->task($request, $team);
        $changes = array_diff_key($request->all(), array_flip(['team_id', 'task_id']));

        SaveTask::handle($team, [...SaveTask::formValues($task), ...$changes], $this->person(), $task);

        return Response::json(['id' => $task->id, 'title' => $task->title, 'when' => $task->fresh()->scheduleSummary(), 'dates' => $task->occurrences()->count()]);
    }
}
