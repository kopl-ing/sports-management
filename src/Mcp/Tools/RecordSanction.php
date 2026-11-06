<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\SportsManagement\Actions\TrackMatch;
use Kopling\SportsManagement\Mcp\MatchTool;
use Kopling\SportsManagement\RefereeDuty;
use Kopling\SportsManagement\SanctionKind;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class RecordSanction extends MatchTool
{
    protected string $name = 'record-sanction';

    protected string $description = 'Records a card, suspension or foul against one of our players; one that rules them out takes them off the field. Only the kinds in live-match\'s sanction_kinds apply to this sport.';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->matchSchema($schema),
            'player_id' => $schema->string()->description('The player id, from live-match.')->required(),
            'kind' => $schema->string()->enum(array_map(fn (SanctionKind $kind) => $kind->value, SanctionKind::cases()))->required(),
            ...$this->minuteSchema($schema),
        ];
    }

    public function handle(Request $request): Response
    {
        $match = $this->trackedMatch($request, RefereeDuty::Sanctions);
        $sanction = TrackMatch::recordSanction($match, array_filter([
            'team_member_id' => $request->get('player_id'),
            'kind' => $request->get('kind'),
            'minute' => $request->get('minute'),
        ], fn ($value) => $value !== null));

        return $this->respond($match, ['recorded' => array_values(array_filter([$sanction->id, $sanction->substitution_id]))]);
    }
}
