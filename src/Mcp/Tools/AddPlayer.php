<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Actions\SaveTeamMember;
use Kopling\SportsManagement\Mcp\TeamTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class AddPlayer extends TeamTool
{
    protected string $name = 'add-player';

    protected string $description = 'Adds a player (or a guest player borrowed for matches) to a team\'s roster. Only a name, no contact details.';

    protected ?string $permission = 'kopling-sports-management::manage-teams';

    public function schema(JsonSchema $schema): array
    {
        return [...$this->teamSchema($schema), ...self::playerSchema($schema, required: true)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function playerSchema(JsonSchema $schema, bool $required): array
    {
        return [
            'name' => $schema->string()->description('The player\'s name as the team uses it.')->required($required),
            'jersey_number' => $schema->string(),
            'positions' => $schema->array()->items($schema->string())->description('Position codes of the team\'s sport, as get-team shows them.'),
            'guest' => $schema->boolean()->description('A player borrowed from another team.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $team = $this->team($request, coachOnly: true);
        $member = SaveTeamMember::handle($team, $request->all(), $this->person());

        return Response::json(self::playerData($member->load('person')));
    }
}
