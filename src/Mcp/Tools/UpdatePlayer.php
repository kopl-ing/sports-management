<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Actions\SaveTeamMember;
use Kopling\SportsManagement\Mcp\TeamTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdatePlayer extends TeamTool
{
    protected string $name = 'update-player';

    protected string $description = 'Changes a roster entry; only the fields given change. A left_on date takes the player off the team from that day: their task sign-ups and the planning of matches not yet played from then on are removed, history stays. Ask the person before setting left_on.';

    protected ?string $permission = 'kopling-sports-management::manage-teams';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'player_id' => $schema->string()->description('The player id, from get-team.')->required(),
            ...AddPlayer::playerSchema($schema, required: false),
            'left_on' => $schema->string()->format('date')->nullable()->description('First day no longer on the team; null puts them back.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $team = $this->team($request, coachOnly: true);
        $member = $this->player($request, $team);

        $current = [
            'name' => $member->person->name,
            'jersey_number' => $member->jersey_number,
            'positions' => $member->positions?->pluck('value')->all() ?? [],
            'guest' => $member->guest,
            'left_on' => $member->left_on?->toDateString(),
        ];
        $changes = array_intersect_key($request->all(), array_flip(['name', 'jersey_number', 'positions', 'guest', 'left_on']));

        SaveTeamMember::handle($team, [...$current, ...$changes], $this->person(), $member);

        return Response::json(self::playerData($member->fresh('person')));
    }
}
