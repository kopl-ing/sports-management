<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Actions\SaveMatch;
use Kopling\SportsManagement\Mcp\TeamTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class PlanMatch extends TeamTool
{
    protected string $name = 'plan-match';

    protected string $description = 'Plans a new match for a team. Confirm the details with the person first.';

    protected ?string $permission = 'kopling-sports-management::manage-matches';

    public function schema(JsonSchema $schema): array
    {
        return [...$this->teamSchema($schema), ...self::matchSchema($schema, required: true)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function matchSchema(JsonSchema $schema, bool $required): array
    {
        return [
            'opponent_name' => $schema->string()->description('The opposing team.')->required($required),
            'home_away' => $schema->string()->enum(['home', 'away'])->required($required),
            'scheduled_at' => $schema->string()->description('Kick-off in the club\'s local time, without a time zone or UTC offset, e.g. 2026-11-28T09:30.')->required($required),
            'location_address' => $schema->string()->description('Where it is played, for away matches.'),
            'play_minutes' => $schema->integer()->min(1)->max(240)->description('Only when different from the team\'s format.'),
            'players_on_field' => $schema->integer()->min(1)->max(30)->description('Only when agreed with the opponent to play with more or fewer than the format; before kick-off only.'),
            'public' => $schema->boolean()->description('Whether parents and guests can follow it through a public link.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $team = $this->team($request, coachOnly: true);
        $match = SaveMatch::handle($team, $request->all());

        return Response::json(ListMatches::summary($team->load('periods'), $match->load(['periods', 'goals'])));
    }
}
