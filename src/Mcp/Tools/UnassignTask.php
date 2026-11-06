<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\TaskEnlistments;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class UnassignTask extends TeamTool
{
    protected string $name = 'unassign-task';

    protected string $description = 'Takes someone off a task as staff. With date_id on a whole-task sign-up, only that date (the regular person is off that day, e.g. ill). Without, a per-date sign-up is removed, and a whole-task sign-up ends before the next date (or is removed when the task has no dates left). Ask the person first.';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'enlistment_id' => $schema->string()->description('The sign-up id, from task-planning.')->required(),
            'date_id' => $schema->string()->description('Whole-task sign-ups: only take them off this date.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $team = $this->tasksTeam($request);
        $enlistment = $this->enlistment($request, $team);
        $occurrence = $this->occurrence($enlistment->task, $request->get('date_id'));

        $occurrence !== null && $enlistment->isWholeTask()
            ? TaskEnlistments::skip($enlistment, $occurrence, $this->person())
            : TaskEnlistments::leave($enlistment, $this->person());

        return Response::text('Done.');
    }
}
