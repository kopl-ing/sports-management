<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Kopling\OAuth\Person;
use Kopling\SportsManagement\Mcp\TeamAccess;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\Team;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListAllTeams extends TeamTool
{
    protected string $name = 'list-all-teams';

    protected string $description = 'Site admins only: every team on the site, including teams hidden by moderation, with counts. Use the ids with the other tools.';

    protected function allows(Person $person): bool
    {
        return TeamAccess::isAdmin($person);
    }

    public function handle(): Response
    {
        return Response::json(Team::withTrashed()->withCount(['staff', 'members', 'matches', 'tasks'])->orderBy('name')->get()->map(fn (Team $team) => array_filter([
            'id' => $team->id,
            'name' => $team->name,
            'club' => $team->club,
            'season' => $team->season,
            'sport' => $team->sport->value,
            'staff' => $team->staff_count,
            'players' => $team->members_count,
            'matches' => $team->matches_count,
            'tasks' => $team->tasks_count,
            'assistant_access' => $team->mcp_enabled,
            'hidden_by_moderation' => $team->trashed() ?: null,
            'hidden_reason' => $team->deleted_reason,
        ], fn ($value) => $value !== null))->values()->all());
    }
}
