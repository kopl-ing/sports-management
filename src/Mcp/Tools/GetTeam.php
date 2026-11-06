<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Mcp\TeamAccess;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\TeamMember;
use Kopling\SportsManagement\TeamPeriod;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetTeam extends TeamTool
{
    protected string $name = 'get-team';

    protected string $description = 'A team\'s details: match format (players on the field, play time split into periods, substitution rules), staff, season periods and settings; for coaches also the roster (current players first, former players flagged with left_on). Base lineups and playing-time plans on the format, never on assumed halves.';

    public function schema(JsonSchema $schema): array
    {
        return $this->teamSchema($schema);
    }

    public function handle(Request $request): Response
    {
        $team = $this->team($request);
        $team->load(['formatPreset', 'staff', 'members.person', 'periods']);
        $coaches = TeamAccess::coaches($this->person(), $team);

        return Response::json(array_filter([
            'id' => $team->id,
            'name' => $team->name,
            'club' => $team->club,
            'season' => $team->season,
            'sport' => $team->sport->value,
            'format' => self::formatData($team->formatPreset),
            'hidden_by_moderation' => $team->trashed() ?: null,
            'staff' => $team->staff->map(fn ($person) => [
                'name' => $person->name,
                'role' => $person->pivot->role,
                'owner' => (bool) $person->pivot->owner,
            ])->values()->all(),
            'periods' => $team->periods->map(fn (TeamPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_on' => $period->starts_on->toDateString(),
                'ends_on' => $period->ends_on->toDateString(),
            ])->values()->all(),
            'settings' => [
                'public_matches' => $team->public_matches,
                'tasks_enabled' => $team->tasks_enabled,
                'tasks_balance_public' => $team->tasks_balance_public,
                'tasks_approve_completion' => $team->tasks_approve_completion,
            ],
            'roster' => $coaches
                ? TeamMember::sorted($team->members)->sortBy(fn (TeamMember $member) => $member->hasLeft())->map(fn (TeamMember $member) => self::playerData($member))->values()->all()
                : null,
        ], fn ($value) => $value !== null));
    }
}
