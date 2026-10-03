@use('Kopling\SportsManagement\MatchState')
{{-- No target: boosted-without-target swaps `document.body`, same as `kopling-moderation::ux.queue-nav`. --}}
<ul class="menu p-4 w-full" hx-boost:inherited="true">
    <li>
        <a href="{{ route('kopling-sports-management::sports-management/teams.index') }}"
           class="@if (request()->routeIs('kopling-sports-management::sports-management/teams.index')) menu-active @endif">
            <x-k::icon name="kopling-sports-management::sports-management" />
            {{ __('kopling-sports-management::messages.all_teams') }}
        </a>
    </li>
    <li class="menu-title">{{ __('kopling-sports-management::messages.teams') }}</li>
    @foreach ($teams as $team)
        <li>
            <a href="{{ route('kopling-sports-management::sports-management/teams.show', $team) }}"
               class="@if ($currentTeam?->is($team) && $currentMatch === null) menu-active @endif">
                <x-k::icon name="kopling-sports-management::team" />
                {{ $team->name }}
            </a>
        </li>
    @endforeach

    @if ($matches->isNotEmpty())
        <li class="menu-title">{{ __('kopling-sports-management::messages.upcoming_matches') }}</li>
        @foreach ($matches as $match)
            @php($live = $match->timeline()->state() === MatchState::Live)
            <li>
                <a href="{{ route('kopling-sports-management::sports-management/matches.'.($live ? 'track' : 'show'), [$match->team, $match]) }}"
                   class="items-start @if ($currentMatch?->is($match)) menu-active @endif">
                    <x-k::icon name="kopling-sports-management::match" class="mt-0.5" />
                    <span class="flex flex-col">
                        <span class="flex items-center gap-2">
                            @if ($live)
                                <span class="status status-success" aria-label="{{ MatchState::Live->label() }}"></span>
                            @endif
                            {{ $teams->count() > 1 ? __('kopling-sports-management::messages.team_versus', ['team' => $match->team->name, 'opponent' => $match->opponent_name]) : $match->opponent_name }}
                        </span>
                        <span class="text-xs opacity-60">
                            {{ $live ? MatchState::Live->label() : $match->scheduled_at->translatedFormat('D j M, H:i') }}
                        </span>
                    </span>
                </a>
            </li>
        @endforeach
    @endif
</ul>
