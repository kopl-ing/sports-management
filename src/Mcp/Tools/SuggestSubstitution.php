<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\MatchState;
use Kopling\SportsManagement\Mcp\MatchTool;
use Kopling\SportsManagement\RotationAdvice;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SuggestSubstitution extends MatchTool
{
    protected string $name = 'suggest-substitution';

    protected string $description = 'During a match: when the next substitution is due to keep playing time equal, and who to swap (off and on, with their minutes). Swaps are put at a break where the bench players can still reach their share, nobody is suggested off before a full period on the field unless a bench player would otherwise fall short, and keepers are left out: they are only swapped when the coach asks. A suggestion, not a substitution: confirm with the coach, then use substitute.';

    protected ?string $permission = null;

    public function schema(JsonSchema $schema): array
    {
        return $this->matchSchema($schema);
    }

    public function handle(Request $request): Response
    {
        $team = $this->team($request);
        $match = $this->match($request, $team)->setRelation('team', $team);
        $this->authorizeDuty($match, null);
        if ($match->timeline()->state() !== MatchState::Live) {
            throw ValidationException::withMessages(['match_id' => 'Only during a match: before kick-off, plan the start with set-lineup.']);
        }

        $now = $match->timeline()->matchSeconds();
        $state = self::state($match);
        $players = collect([...$state['on_field'], ...$state['bench']])->keyBy('id');
        $available = $players->reject(fn (array $player) => $player['unavailable'] ?? false)->keys()->all();
        $advice = new RotationAdvice($match, $available);
        $next = $advice->next();
        $target = $advice->targetSeconds();

        return Response::json(array_filter([
            'minute' => intdiv($now, 60),
            'rotation_play_minutes_per_player' => $target === null ? null : intdiv($target, 60),
            'next' => $next === null ? null : [
                'at_minute' => intdiv($next['second'], 60),
                'in_minutes' => intdiv($next['second'] - $now, 60),
                'at_break' => $next['at_break'],
                'swaps' => array_map(fn (array $swap) => ['off' => $players[$swap['off']], 'on' => $players[$swap['on']]], $next['swaps']),
            ],
            'note' => $next === null ? 'No substitution needed: every bench player reaches their share without one.' : null,
        ], fn ($value) => $value !== null));
    }
}
