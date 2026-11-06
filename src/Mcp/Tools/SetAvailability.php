<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Actions\SetAvailability as SetAvailabilityAction;
use Kopling\SportsManagement\Mcp\TeamTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class SetAvailability extends TeamTool
{
    protected string $name = 'set-availability';

    protected string $description = 'Sets whether players can play a match: available, maybe or absent (null clears it). Players not given stay as they are.';

    protected ?string $permission = 'kopling-sports-management::manage-matches';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'match_id' => $schema->string()->description('The match id, from list-matches.')->required(),
            'availability' => $schema->object()->description('Player id (from get-team) to "available", "maybe", "absent" or null.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $team = $this->team($request, coachOnly: true);
        $match = $this->match($request, $team)->setRelation('team', $team);
        SetAvailabilityAction::handle($match, (array) $request->get('availability'), replace: false);

        return Response::json($match->availabilities()->with('teamMember.person')->get()->map(fn ($row) => [
            'player' => $row->teamMember?->person->name,
            'status' => $row->status->value,
        ])->values()->all());
    }
}
