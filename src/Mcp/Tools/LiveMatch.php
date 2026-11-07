<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Mcp\MatchTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class LiveMatch extends MatchTool
{
    protected string $name = 'live-match';

    protected string $description = 'The match as it stands: clock, score, who is on the field and on the bench (with player ids, zones, minutes played and on the bench, minutes since their last substitution, and minutes still to go to the equal share, negative once past it), the equal share of play and bench minutes per player to rotate towards, and the latest events with their ids. Call this first to find the players named during a match.';

    protected ?string $permission = null;

    public function schema(JsonSchema $schema): array
    {
        return $this->matchSchema($schema);
    }

    public function handle(Request $request): Response
    {
        $team = $this->team($request);
        $match = $this->match($request, $team)->setRelation('team', $team);
        if (! $match->isVisibleTo($this->person())) {
            throw new AuthorizationException('Match not found.');
        }

        return $this->respond($match);
    }
}
