<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\Actions\TrackMatch;
use Kopling\SportsManagement\MatchGoal;
use Kopling\SportsManagement\MatchSanction;
use Kopling\SportsManagement\Mcp\MatchTool;
use Kopling\SportsManagement\RefereeDuty;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteMatchEvent extends MatchTool
{
    protected string $name = 'delete-match-event';

    protected string $description = 'Takes back recorded goals, substitutions or sanctions, by the ids a record tool returned as "recorded" or live-match lists. Pass every id of one action (a swap has two) to undo it whole.';

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->matchSchema($schema),
            'event_ids' => $schema->array()->items($schema->string())->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $team = $this->team($request);
        $match = $this->match($request, $team)->setRelation('team', $team);
        $ids = array_values(array_filter((array) $request->get('event_ids'), 'is_string'));

        $events = collect()
            ->concat($match->goals()->whereKey($ids)->get())
            ->concat($match->sanctions()->whereKey($ids)->get())
            ->concat($match->substitutions()->whereKey($ids)->get());
        if ($ids === [] || $events->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['event_ids' => 'One or more events were not found in this match.']);
        }

        foreach ($events as $event) {
            $this->authorizeDuty($match, match (true) {
                $event instanceof MatchGoal => RefereeDuty::Scoring,
                $event instanceof MatchSanction => RefereeDuty::Sanctions,
                default => null,
            });
        }

        DB::transaction(fn () => $events->each(fn ($event) => $event instanceof MatchSanction ? TrackMatch::deleteSanction($event) : $event->delete()));

        return $this->respond($match, ['deleted' => $ids]);
    }
}
