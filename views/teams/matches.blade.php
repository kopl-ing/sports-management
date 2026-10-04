<section class="card card-border bg-base-100">
    <div class="card-body gap-3">
        <div class="flex items-center justify-between">
            <h2 class="card-title">{{ __('kopling-sports-management::messages.matches') }}</h2>

            @if ($canManageMatches)
                <x-k::modal :label="__('kopling-sports-management::messages.plan_match')" id="modal-match-create">
                    <x-slot:trigger>{{ __('kopling-sports-management::messages.plan_match') }}</x-slot:trigger>
                    @include('kopling-sports-management::matches.form', [
                        'action' => route('kopling-sports-management::sports-management/matches.store', $team),
                        'formId' => 'modal-match-create',
                        'title' => __('kopling-sports-management::messages.plan_match'),
                        'match' => null,
                    ])
                </x-k::modal>
            @endif
        </div>

        @if ($upcomingMatches->isEmpty() && $pastMatches->isEmpty())
            <p class="opacity-60">{{ __('kopling-sports-management::messages.no_matches') }}</p>
        @endif

        @foreach (['upcoming_matches' => $upcomingMatches, 'past_matches' => $pastMatches] as $heading => $matches)
            @if ($matches->isNotEmpty())
                <h3 class="text-sm font-semibold opacity-60">{{ __('kopling-sports-management::messages.'.$heading) }}</h3>
                <ul class="list">
                    @foreach ($matches as $match)
                        <li>
                            <a href="{{ \Kopling\SportsManagement\MatchState::fromPeriods($match->periods) === \Kopling\SportsManagement\MatchState::Ended
                                   ? route('kopling-sports-management::sports-management/matches.report', [$team, $match])
                                   : route('kopling-sports-management::sports-management/matches.show', [$team, $match]) }}"
                               class="list-row flex items-center justify-between gap-2 hover:bg-base-200">
                                <span class="min-w-0">
                                    <span class="block font-semibold">{{ $match->opponent_name }}</span>
                                    <span class="block text-sm opacity-60">{{ $match->scheduled_at->translatedFormat('D j M Y, H:i') }}</span>
                                </span>
                                <span class="flex shrink-0 items-center gap-2 whitespace-nowrap">
                                    @if ($match->periods->isNotEmpty())
                                        <span class="font-semibold tabular-nums">{{ $match->goals->where('opponent', false)->sum('points') }} &ndash; {{ $match->goals->where('opponent', true)->sum('points') }}</span>
                                    @endif
                                    <span class="badge badge-outline" title="{{ $match->home_away->label() }}">
                                        <x-k::icon :name="'kopling-sports-management::'.$match->home_away->value" />
                                        <span class="sr-only sm:not-sr-only">{{ $match->home_away->label() }}</span>
                                    </span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    </div>
</section>
