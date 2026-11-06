<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskEnlistments;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\TaskSchedule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class AssignTask extends TeamTool
{
    protected string $name = 'assign-task';

    protected string $description = 'Puts a player\'s family on a task as staff: on one date (date_id), or for every date when no date is given and the task is taken on as a whole. Putting a regular person back on a date they were taken off restores them. Works on past dates too, which then count as done.';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'task_id' => $schema->string()->description('The task id, from list-tasks or task-planning.')->required(),
            'player_id' => $schema->string()->description('The player id, from get-team.')->required(),
            'date_id' => $schema->string()->description('A date id from task-planning; leave out for the whole task.'),
            'note' => $schema->string()->max(100)->description('Who actually does it, e.g. "dad, with the van".'),
            'starts_on' => $schema->string()->format('date')->description('Whole task only: from which date it counts, default today.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:100'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $team = $this->tasksTeam($request);
        $task = $this->task($request, $team);
        $member = $this->player($request, $team);
        $occurrence = $this->occurrence($task, $request->get('date_id'));
        if ($occurrence === null && $task->enrolment === TaskEnrolment::PerOccurrence) {
            throw new AuthorizationException('This task is taken on date by date: give a date_id from task-planning.');
        }
        if ($occurrence !== null && $task->schedule === TaskSchedule::Season) {
            throw new AuthorizationException('A fixed role is taken on as a whole: leave out date_id.');
        }

        $skipped = $occurrence === null ? null : $task->wholeTaskEnlistments()->where('team_member_id', $member->id)->get()
            ->first(fn (TaskEnlistment $enlistment) => $enlistment->isSkippedOn($occurrence));
        if ($skipped !== null) {
            TaskEnlistments::restore($skipped->setRelation('task', $task), $occurrence, $this->person());

            return Response::text($member->person->name.' is back on '.$task->title.' on '.$occurrence->date->toDateString().'.');
        }

        $enlistment = TaskEnlistments::enlist(
            $task,
            $occurrence,
            $member,
            $data['note'] ?? null,
            $this->person(),
            isset($data['starts_on']) ? CarbonImmutable::parse($data['starts_on']) : null,
        );

        return Response::json(['enlistment_id' => $enlistment->id, 'player' => $member->person->name, 'task' => $task->title, 'date' => $occurrence?->date->toDateString()]);
    }
}
