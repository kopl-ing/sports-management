<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;
use Kopling\SportsManagement\MatchState;
use Kopling\SportsManagement\StaffRole;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMatch;

class TeamsNav extends Component
{
    public const MATCHES_UP_TO_TEAMS = 3;

    public const MATCH_COUNT = 3;

    public function __construct(
        protected Request $request,
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function shouldRender(): bool
    {
        return $this->request->user() !== null;
    }

    public function render(): View
    {
        $user = $this->request->user();
        $teams = Team::query()
            ->whereHas('staff', fn ($query) => $query->whereKey($user->id))
            ->with(['staff' => fn ($query) => $query->whereKey($user->id)])
            ->orderBy('name')
            ->get();
        [$coached, $refereed] = $teams->partition(fn (Team $team) => $team->staff->first()?->pivot->role === StaffRole::Coach->value);

        $matches = $teams->isEmpty() || $teams->count() > self::MATCHES_UP_TO_TEAMS
            ? collect()
            : TeamMatch::query()
                ->where(fn ($query) => $query
                    ->whereIn('team_id', $coached->modelKeys())
                    ->orWhere(fn ($query) => $query->whereIn('team_id', $refereed->modelKeys())->where('referee_person_id', $user->id)))
                ->where('scheduled_at', '>=', now()->startOfDay())
                ->with(['team', 'periods'])
                ->orderBy('scheduled_at')
                ->get()
                ->reject(fn (TeamMatch $match) => $match->timeline()->state() === MatchState::Ended)
                ->take(self::MATCH_COUNT);

        return view('kopling-sports-management::ux.teams-nav', [
            'teams' => $teams,
            'matches' => $matches,
            'currentTeam' => $this->request->route('team'),
            'currentMatch' => $this->request->route('teamMatch'),
        ]);
    }
}
