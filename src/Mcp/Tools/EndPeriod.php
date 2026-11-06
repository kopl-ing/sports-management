<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\Actions\TrackMatch;
use Kopling\SportsManagement\Mcp\MatchTool;
use Kopling\SportsManagement\RefereeDuty;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class EndPeriod extends MatchTool
{
    protected string $name = 'end-period';

    protected string $description = 'Stops the running period now without starting another, e.g. the final whistle. For a break use start-period with "break" instead.';

    public function schema(JsonSchema $schema): array
    {
        return $this->matchSchema($schema);
    }

    public function handle(Request $request): Response
    {
        $match = $this->trackedMatch($request, RefereeDuty::Timing);
        $running = $match->timeline()->runningPeriod()
            ?? throw ValidationException::withMessages(['period' => 'No period is running.']);
        TrackMatch::endPeriod($match, $running);

        return $this->respond($match);
    }
}
