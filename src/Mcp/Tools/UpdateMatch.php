<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Actions\SaveMatch;
use Kopling\SportsManagement\Mcp\TeamTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateMatch extends TeamTool
{
    protected string $name = 'update-match';

    protected string $description = 'Changes a planned match; only the fields given change. Moving it also moves the tasks tied to it.';

    protected ?string $permission = 'kopling-sports-management::manage-matches';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'match_id' => $schema->string()->description('The match id, from list-matches.')->required(),
            ...PlanMatch::matchSchema($schema, required: false),
        ];
    }

    public function handle(Request $request): Response
    {
        $team = $this->team($request, coachOnly: true);
        $match = $this->match($request, $team);

        $current = [
            'opponent_name' => $match->opponent_name,
            'home_away' => $match->home_away->value,
            'scheduled_at' => $match->scheduled_at->format('Y-m-d H:i'),
            'location_address' => $match->location_address,
            'format_preset_id' => $match->format_preset_id,
            'play_minutes' => $match->play_minutes,
            'players_on_field' => $match->players_on_field,
            'referee_person_id' => $match->referee_person_id,
            'referee_duties' => $match->referee_duties ? collect($match->referee_duties)->map->value->all() : null,
            'public' => $match->public,
        ];
        $changes = array_intersect_key($request->all(), array_flip(['opponent_name', 'home_away', 'scheduled_at', 'location_address', 'play_minutes', 'players_on_field', 'public']));

        SaveMatch::handle($team, [...$current, ...$changes], $match);

        return Response::json(ListMatches::summary($team->load('periods'), $match->fresh(['periods', 'goals'])));
    }
}
