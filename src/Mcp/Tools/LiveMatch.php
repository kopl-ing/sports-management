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

    protected string $description = 'The match as it stands: clock, score, who is on the field and on the bench (with player ids and zones), and the latest events with their ids. Call this first to find the players named during a match.';

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
