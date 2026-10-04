<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;
use Kopling\SportsManagement\PeriodType;
use Kopling\SportsManagement\TeamMatch;

class MatchControls extends Component
{
    public function __construct(
        protected Request $request,
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function shouldRender(): bool
    {
        return $this->request->routeIs('kopling-sports-management::sports-management/matches.track')
            && $this->request->route('teamMatch') instanceof TeamMatch;
    }

    public function render(): View
    {
        $match = $this->request->route('teamMatch');
        $match->setRelation('team', $this->request->route('team'));
        $timeline = $match->timeline();
        $running = $timeline->runningPeriod();

        return view('kopling-sports-management::ux.match-controls', [
            'team' => $this->request->route('team'),
            'match' => $match,
            'timeline' => $timeline,
            'state' => $timeline->state(),
            'score' => $timeline->score(),
            'running' => $running,
            'playPeriodSeconds' => $match->playPeriodSeconds(),
            'pointValues' => $match->sportConfig()->pointValues(),
            'ticking' => $running?->type === PeriodType::Play,
            'canTrack' => Gate::allows('kopling-sports-management::track-matches'),
            'duties' => $match->dutiesOf($this->request->user()),
        ]);
    }
}
