<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\FieldMove;
use Kopling\SportsManagement\MatchLineup;
use Kopling\SportsManagement\Mcp\TeamTool;
use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\TeamMatch;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class SetLineup extends TeamTool
{
    protected string $name = 'set-lineup';

    protected string $description = 'Sets the starting lineup of a match before kick-off: who starts on the field, and in which zone. Replaces the whole lineup; available players left out start on the bench. At kick-off this lineup goes on the field; after that, use substitute. Confirm the lineup with the coach first.';

    protected ?string $permission = 'kopling-sports-management::manage-matches';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'match_id' => $schema->string()->description('The match id, from list-matches.')->required(),
            'lineup' => $schema->array()->items($schema->object(fn ($schema) => [
                'player_id' => $schema->string()->description('Player id, from get-team.')->required(),
                'zone' => $schema->string()->description('Zone value, from get-match\'s zones.')->required(),
            ]))->description('The starting players. Within a zone, list them in the order they stand on the field. An empty list clears the lineup.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $team = $this->team($request, coachOnly: true);
        $match = $this->match($request, $team)->setRelation('team', $team);
        if ($match->periods()->exists()) {
            throw ValidationException::withMessages(['match_id' => 'The match has started: use substitute to change who is on the field.']);
        }

        $placement = $this->placement($match, (array) $request->get('lineup'));
        $preset = $match->effectiveFormatPreset();
        if ($error = FieldMove::limitError([], $placement, $match->effectivePlayersOnField(), $match->sportConfig()->keeperZone($preset))) {
            throw ValidationException::withMessages(['lineup' => $error]);
        }

        DB::transaction(function () use ($match, $placement) {
            $match->lineup()->whereNotIn('team_member_id', array_keys($placement))->delete();
            $match->slots()->delete();
            $slots = [];
            foreach ($placement as $memberId => $zone) {
                $match->lineup()->updateOrCreate(['team_member_id' => $memberId], ['zone' => $zone]);
                $slot = $slots[$zone->value] = ($slots[$zone->value] ?? 0) + 1;
                $match->slots()->create(['team_member_id' => $memberId, 'zone' => $zone, 'slot' => (float) $slot]);
            }
        });

        $names = $team->members()->with('person')->get()->keyBy('id');
        $available = array_diff($team->members()->onTeamAt($match->scheduled_at)->pluck('id')->all(), $match->absentMemberIds());

        return Response::json([
            'lineup' => $match->lineup()->get()->sortBy(fn (MatchLineup $row) => array_search($row->team_member_id, array_keys($placement), true))->map(fn (MatchLineup $row) => [
                'player' => $names->get($row->team_member_id)?->person->name,
                'zone' => $row->zone?->value,
            ])->values()->all(),
            'bench' => $names->only(array_diff($available, array_keys($placement)))->map(fn ($member) => $member->person->name)->values()->all(),
            'players_on_field' => $match->effectivePlayersOnField(),
            'equal_share' => self::equalShare($match, count($available)),
        ]);
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<string, Position> zone per team member id, in the given order
     */
    private function placement(TeamMatch $match, array $rows): array
    {
        $members = $match->team->members()->onTeamAt($match->scheduled_at)->pluck('id')->all();
        $absent = $match->absentMemberIds();
        $zones = $match->sportConfig()->zones($match->effectiveFormatPreset());
        $placement = [];

        foreach ($rows as $row) {
            $id = (string) ($row['player_id'] ?? '');
            $zone = Position::tryFrom((string) ($row['zone'] ?? ''));
            $error = match (true) {
                ! in_array($id, $members, true) => "Player {$id} is not on this team's roster for this match.",
                in_array($id, $absent, true) => "Player {$id} is marked absent for this match.",
                array_key_exists($id, $placement) => "Player {$id} is listed twice.",
                $zone === null || ! in_array($zone, $zones, true) => 'Zone "'.($row['zone'] ?? '').'" does not exist here; use one of: '.implode(', ', array_map(fn (Position $zone) => $zone->value, $zones)).'.',
                default => null,
            };
            if ($error !== null) {
                throw ValidationException::withMessages(['lineup' => $error]);
            }
            $placement[$id] = $zone;
        }

        return $placement;
    }
}
