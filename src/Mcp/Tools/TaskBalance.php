<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\TaskBalance as Balance;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class TaskBalance extends TeamTool
{
    protected string $name = 'task-balance';

    protected string $description = 'How the task load is spread over the players\' families: points earned (task weight per date done), points planned, no-shows and dates still to confirm; lowest first, former players marked. Coaches only.';

    public function schema(JsonSchema $schema): array
    {
        return $this->teamSchema($schema);
    }

    public function handle(Request $request): Response
    {
        return Response::json(Balance::for($this->tasksTeam($request))->map(fn (array $row) => array_filter([
            'player' => $row['member']->person->name,
            'former_player' => $row['left'] ?: null,
            'points' => $row['points'],
            'planned_points' => $row['planned'],
            'done' => $row['done'],
            'upcoming' => $row['upcoming'],
            'no_shows' => $row['no_shows'] ?: null,
            'to_confirm' => $row['pending'] ?: null,
        ], fn ($value) => $value !== null))->values()->all());
    }
}
