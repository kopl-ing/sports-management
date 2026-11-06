<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Mcp\TeamTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class CancelMatch extends TeamTool
{
    protected string $name = 'cancel-match';

    protected string $description = 'Deletes a match that has not started, with its lineup and availability. Tasks tied to it are cancelled. Always ask the person first.';

    protected ?string $permission = 'kopling-sports-management::manage-matches';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'match_id' => $schema->string()->description('The match id, from list-matches.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $team = $this->team($request, coachOnly: true);
        $match = $this->match($request, $team);
        if ($match->periods()->exists()) {
            throw new AuthorizationException('This match has been played or is being played; it can only be deleted on the site.');
        }

        $match->delete();

        return Response::text('Match against '.$match->opponent_name.' deleted.');
    }
}
