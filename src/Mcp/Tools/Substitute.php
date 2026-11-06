<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\Actions\TrackMatch;
use Kopling\SportsManagement\Mcp\MatchTool;
use Kopling\SportsManagement\TeamMatch;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class Substitute extends MatchTool
{
    protected string $name = 'substitute';

    protected string $description = 'Changes who is on the field. Both players: the one coming on takes over the zone of the one going off (two field players swap zones). Only off_player_id: off without replacement. Only on_player_id: comes on in the given zone. A zone given with both moves the incoming player there afterwards.';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->matchSchema($schema),
            'on_player_id' => $schema->string()->description('Player coming on, from live-match.'),
            'off_player_id' => $schema->string()->description('Player going off, from live-match.'),
            'zone' => $schema->string()->description('Zone value from live-match\'s zones.'),
            ...$this->minuteSchema($schema),
        ];
    }

    public function handle(Request $request): Response
    {
        $match = $this->trackedMatch($request, null);
        $on = $request->get('on_player_id');
        $off = $request->get('off_player_id');
        $zone = $request->get('zone');
        $minute = $request->get('minute');

        if ($on === null && $off === null) {
            throw ValidationException::withMessages(['on_player_id' => 'Give the player coming on, going off, or both.']);
        }

        $ids = $minute === null ? $this->live($match, $on, $off, $zone) : $this->earlier($match, $on, $off, $zone, (int) $minute);

        return $this->respond($match, ['recorded' => $ids]);
    }

    /**
     * @return array<int, string>
     */
    private function live(TeamMatch $match, ?string $on, ?string $off, ?string $zone): array
    {
        if ($off !== null && ! array_key_exists($off, $match->timeline()->onField())) {
            throw ValidationException::withMessages(['off_player_id' => 'That player is not on the field.']);
        }
        if ($on !== null && $off === null && $zone === null) {
            throw ValidationException::withMessages(['zone' => 'Give the zone the player comes on in.']);
        }

        $ids = TrackMatch::moveOnField($match, array_filter([
            'team_member_id' => $on ?? $off,
            'replace_team_member_id' => $on === null ? null : $off,
            'zone' => $off === null ? $zone : null,
        ]));

        if ($on !== null && $off !== null && $zone !== null) {
            $match->unsetRelations()->setRelation('team', $match->team);
            $ids = [...$ids, ...TrackMatch::moveOnField($match, ['team_member_id' => $on, 'zone' => $zone])];
        }

        return $ids;
    }

    /**
     * @return array<int, string>
     */
    private function earlier(TeamMatch $match, ?string $on, ?string $off, ?string $zone, int $minute): array
    {
        if ($on !== null && $zone === null && $off !== null) {
            $timeline = $match->timeline();
            $moment = $timeline->momentAt($minute * 60);
            $zone = $moment === null ? null : ($timeline->onFieldAt(...$moment)[$off] ?? $match->sportConfig()->defaultZone())?->value;
        }

        return TrackMatch::substitute($match, array_filter([
            'on_team_member_id' => $on,
            'off_team_member_id' => $off,
            'zone' => $on === null ? null : $zone,
            'minute' => $minute,
        ], fn ($value) => $value !== null));
    }
}
