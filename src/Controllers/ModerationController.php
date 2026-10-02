<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Kopling\Core\Ux\Context;
use Kopling\SportsManagement\Team;

class ModerationController
{
    public const SORTS = ['newest', 'roster', 'matches'];

    /**
     * Metadata only: roster names and match details are shown solely in a reported team's queue preview.
     */
    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'newest';

        $query = Team::withTrashed()
            ->with(['staff', 'formatPreset'])
            ->withCount(['members', 'matches'])
            ->when($sort === 'newest', fn ($query) => $query->latest())
            ->when($sort === 'roster', fn ($query) => $query->orderByDesc('members_count'))
            ->when($sort === 'matches', fn ($query) => $query->orderByDesc('matches_count'));

        return view('kopling-sports-management::moderation.teams', [
            'context' => new Context(subject: $query),
            'sort' => $sort,
        ]);
    }
}
