<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Kopling\SportsManagement\Mcp\TeamAccess;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\Team;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListMyTeams extends TeamTool
{
    protected string $name = 'list-my-teams';

    protected string $description = 'Lists the teams you are staff of (coach or referee) that you can work with through an assistant. Use the ids with the other tools.';

    public function handle(): Response
    {
        $person = $this->person();

        return Response::json(TeamAccess::teams($person)->map(fn (Team $team) => [
            'id' => $team->id,
            'name' => $team->name,
            'club' => $team->club,
            'season' => $team->season,
            'sport' => $team->sport->value,
            'format' => $team->formatPreset?->name,
            'role' => TeamAccess::role($person, $team)?->value,
            'tasks_enabled' => $team->tasks_enabled,
        ])->values()->all());
    }
}
