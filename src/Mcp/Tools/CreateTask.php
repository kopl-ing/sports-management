<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Actions\SaveTask;
use Kopling\SportsManagement\Mcp\TeamTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class CreateTask extends TeamTool
{
    protected string $name = 'create-task';

    protected string $description = 'Creates a volunteer task for a team (e.g. driving to away matches, a weekly trainer, a fixed season role). Confirm the details with the person first.';

    protected ?string $permission = 'kopling-sports-management::manage-teams';

    public function schema(JsonSchema $schema): array
    {
        return [...$this->teamSchema($schema), ...self::taskSchema($schema, required: true)];
    }

    /**
     * Same fields as the task form.
     *
     * @return array<string, mixed>
     */
    public static function taskSchema(JsonSchema $schema, bool $required): array
    {
        return [
            'title' => $schema->string()->max(255)->required($required),
            'description' => $schema->string()->max(5000),
            'schedule' => $schema->string()->enum(['all_matches', 'home_matches', 'away_matches', 'dates', 'recurring', 'season'])
                ->description('Matches: a date per match. dates: the given dates. recurring: a rule (frequency, interval, weekday...). season: a fixed role from starts_on to ends_on, earning its weight per month.')->required($required),
            'enrolment' => $schema->string()->enum(['per_occurrence', 'whole_task'])->description('per_occurrence: people take dates one by one. whole_task: one sign-up covers every date (trainer, coordinator).')->required($required),
            'undo_window' => $schema->string()->enum(['any_time', 'hour_before', 'day_before', 'never'])->description('Until when parents can back out through the link.')->required($required),
            'staff_only' => $schema->boolean()->description('Only staff assign people; parents can\'t take it on.'),
            'lead_minutes' => $schema->integer()->min(0)->max(1440)->description('Match schedules: minutes before kick-off.'),
            'dates' => $schema->array()->items($schema->object(fn ($schema) => ['date' => $schema->string()->format('date')->required(), 'time' => $schema->string()]))->description('schedule "dates": the dates, time optional (HH:MM).'),
            'frequency' => $schema->string()->enum(['weekly', 'monthly', 'yearly'])->description('schedule "recurring".'),
            'interval' => $schema->integer()->min(1)->max(52)->description('Every how many weeks or months.'),
            'weekday' => $schema->integer()->min(1)->max(7)->description('1 = Monday ... 7 = Sunday.'),
            'monthly_by' => $schema->string()->enum(['day', 'nth']),
            'day' => $schema->integer()->min(1)->max(31),
            'nth' => $schema->integer()->enum([1, 2, 3, 4, -1])->description('-1 = last.'),
            'month' => $schema->integer()->min(1)->max(12),
            'time' => $schema->string()->description('HH:MM, for recurring tasks.'),
            'starts_on' => $schema->string()->format('date')->description('recurring and season.'),
            'ends_on' => $schema->string()->format('date')->description('recurring and season, at most two years after starts_on.'),
            'within_periods' => $schema->boolean()->description('recurring and season: only within the team\'s periods.'),
            'min_people' => $schema->integer()->min(1)->max(99),
            'max_people' => $schema->integer()->min(1)->max(99),
            'effort_minutes' => $schema->integer()->min(1)->max(10000),
            'weight' => $schema->integer()->min(1)->max(10)->description('Points one date earns in the balance.')->required($required),
        ];
    }

    public function handle(Request $request): Response
    {
        $team = $this->tasksTeam($request);
        $task = SaveTask::handle($team, $request->all(), $this->person());

        return Response::json(['id' => $task->id, 'title' => $task->title, 'when' => $task->scheduleSummary(), 'dates' => $task->occurrences()->count()]);
    }
}
