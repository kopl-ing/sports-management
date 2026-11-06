<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Actions\TrackMatch;
use Kopling\SportsManagement\Mcp\MatchTool;
use Kopling\SportsManagement\RefereeDuty;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class StartPeriod extends MatchTool
{
    protected string $name = 'start-period';

    protected string $description = 'Starts the clock now: "play" for kick-off or the restart after a break, "break" when a break starts. Ends whichever period is running. The first kick-off puts the planned lineup on the field.';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->matchSchema($schema),
            'type' => $schema->string()->enum(['play', 'break'])->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $match = $this->trackedMatch($request, RefereeDuty::Timing);
        TrackMatch::startPeriod($match, ['type' => $request->get('type')]);

        return $this->respond($match);
    }
}
