<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Actions\TrackMatch;
use Kopling\SportsManagement\Mcp\MatchTool;
use Kopling\SportsManagement\RefereeDuty;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class RecordGoal extends MatchTool
{
    protected string $name = 'record-goal';

    protected string $description = 'Records a goal for us (with scorer and assist when known) or for the opponent. The same goal sent twice within seconds is recorded once.';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->matchSchema($schema),
            'for' => $schema->string()->enum(['us', 'opponent'])->required(),
            'scorer_id' => $schema->string()->description('Our scorer\'s player id, from live-match.'),
            'assist_id' => $schema->string()->description('Our player id who gave the assist.'),
            'own_goal' => $schema->boolean()->description('An opponent\'s own goal, counted for us; no scorer.'),
            'points' => $schema->integer()->description('Only for sports where a score can be worth more than one (see point_values in live-match).'),
            ...$this->minuteSchema($schema),
        ];
    }

    public function handle(Request $request): Response
    {
        $match = $this->trackedMatch($request, RefereeDuty::Scoring);
        $goal = TrackMatch::recordGoal($match, array_filter([
            'opponent' => $request->get('for') === 'opponent',
            'own_goal' => $request->get('own_goal'),
            'points' => $request->get('points'),
            'scorer_team_member_id' => $request->get('scorer_id'),
            'assist_team_member_id' => $request->get('assist_id'),
            'minute' => $request->get('minute'),
        ], fn ($value) => $value !== null));

        return $this->respond($match, ['recorded' => $goal ? [$goal->id] : [], 'duplicate' => $goal === null ?: null]);
    }
}
