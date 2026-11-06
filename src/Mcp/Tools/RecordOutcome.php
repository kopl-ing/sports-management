<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\TaskEnlistments;
use Kopling\SportsManagement\TaskOutcome;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class RecordOutcome extends TeamTool
{
    protected string $name = 'record-outcome';

    protected string $description = 'Marks a sign-up on a passed date as done or no_show, for teams that confirm what was done. Only "done" counts in the balance.';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'enlistment_id' => $schema->string()->description('The sign-up id, from task-planning with past_weeks.')->required(),
            'date_id' => $schema->string()->description('The passed date\'s id.')->required(),
            'outcome' => $schema->string()->enum(['done', 'no_show'])->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate(['outcome' => ['required', 'in:done,no_show']]);
        $team = $this->tasksTeam($request);
        $enlistment = $this->enlistment($request, $team);
        $occurrence = $this->occurrence($enlistment->task, $request->get('date_id'))
            ?? throw new AuthorizationException('Give the date_id.');
        if (! $team->tasks_approve_completion) {
            throw new AuthorizationException('This team does not confirm what was done; every passed date counts.');
        }
        if ($occurrence->cancelled_at !== null || ! $occurrence->hasPassed() || ! $enlistment->covers($occurrence)) {
            throw new AuthorizationException('That sign-up isn\'t on this date, or the date hasn\'t passed yet.');
        }

        TaskEnlistments::recordOutcome($enlistment, $occurrence, TaskOutcome::from($data['outcome']), $this->person());

        return Response::text('Recorded '.$data['outcome'].'.');
    }
}
