@use('Kopling\SportsManagement\MatchState')
@use('Kopling\SportsManagement\PeriodType')
@php
    $timing = $canTrack && $duties['timing'];
    $scoring = $canTrack && $duties['scoring'];
    $readOnly = ! $timing || $state === MatchState::Ended;
@endphp
<div data-sm-controls hx-replace-url:inherited="true" @class(['w-full items-center gap-4', 'grid grid-cols-[1fr_auto_1fr]' => $readOnly, 'flex justify-between' => ! $readOnly])>
    @if ($readOnly)
    <div class="flex items-center justify-end gap-2">
        @if ($running?->type === PeriodType::Break)
            <span class="badge badge-warning badge-sm" title="{{ __('kopling-sports-management::messages.break') }}">
                @include('kopling-sports-management::matches.tracking.clock', ['seconds' => $timeline->length($running), 'ticking' => true])
            </span>
        @elseif ($state !== MatchState::Planned)
            @include('kopling-sports-management::matches.tracking.clock', [
                'seconds' => $timeline->matchSeconds(),
                'ticking' => $ticking,
                'class' => 'font-mono tabular-nums',
            ])
        @endif
    </div>
    @endif

    <div class="flex items-center gap-2">
        <span class="text-xl font-bold tabular-nums whitespace-nowrap">{{ $score['us'] }}&ndash;{{ $score['them'] }}</span>
        @if ($scoring && $state === MatchState::Live)
            @if (count($pointValues) === 1)
                <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.goals.store', [$team, $match]) }}" hx-boost="true">
                    @csrf
                    <input type="hidden" name="opponent" value="1">
                    <input type="hidden" name="points" value="{{ $pointValues[0] }}">
                    <button type="submit" class="btn btn-xs btn-outline btn-error"
                            aria-label="{{ $team->sport->trans('goal_opponent', ['opponent' => $match->opponent_name]) }}">+{{ $pointValues[0] }}</button>
                </form>
            @else
                <details class="dropdown dropdown-center" data-sm-opponent-points>
                    <summary class="btn btn-xs btn-outline btn-error" aria-label="{{ $team->sport->trans('goal_opponent', ['opponent' => $match->opponent_name]) }}">+</summary>
                    <ul class="dropdown-content menu bg-base-100 rounded-box z-10 mt-1 p-1 shadow-sm">
                        @foreach ($pointValues as $points)
                            <li>
                                <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.goals.store', [$team, $match]) }}" hx-boost="true">
                                    @csrf
                                    <input type="hidden" name="opponent" value="1">
                                    <input type="hidden" name="points" value="{{ $points }}">
                                    <button type="submit" class="btn btn-sm btn-ghost text-error tabular-nums">+{{ $points }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        @endif
    </div>

    <div class="flex items-center justify-between gap-4">
        @if ($timing)
            @if ($state !== MatchState::Ended)
                <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.periods.start', [$team, $match]) }}" hx-boost="true">
                    @csrf
                    @if ($running?->type === PeriodType::Play)
                        @php($breakIn = $playPeriodSeconds === null ? null : $playPeriodSeconds - $timeline->length($running))
                        <input type="hidden" name="type" value="{{ PeriodType::Break->value }}">
                        <button type="submit" data-sm-break-due="{{ $breakIn !== null && $breakIn <= 0 ? 'now' : '' }}"
                                class="btn btn-sm gap-1.5 data-[sm-break-due=now]:btn-warning data-[sm-break-due=now]:animate-pulse"
                                @if ($breakIn !== null && $breakIn > 0)
                                    x-data x-init="setTimeout(() => { $el.dataset.smBreakDue = 'now'; kopling.alert() }, {{ $breakIn * 1000 }})"
                                @endif
                                aria-label="{{ __('kopling-sports-management::messages.break') }}" title="{{ __('kopling-sports-management::messages.break') }}">
                            <x-k::icon name="kopling-sports-management::pause" />
                            @include('kopling-sports-management::matches.tracking.clock', ['seconds' => $timeline->matchSeconds(), 'ticking' => true])
                        </button>
                    @elseif ($running?->type === PeriodType::Break)
                        <input type="hidden" name="type" value="{{ PeriodType::Play->value }}">
                        <button type="submit" class="btn btn-sm btn-warning gap-1.5"
                                aria-label="{{ __('kopling-sports-management::messages.continue') }}" title="{{ __('kopling-sports-management::messages.continue') }}">
                            <x-k::icon name="kopling-sports-management::play" />
                            @include('kopling-sports-management::matches.tracking.clock', ['seconds' => $timeline->length($running), 'ticking' => true])
                        </button>
                    @else
                        <input type="hidden" name="type" value="{{ PeriodType::Play->value }}">
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('kopling-sports-management::messages.kick_off') }}</button>
                    @endif
                </form>
            @endif

            @if ($state === MatchState::Live)
                <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.periods.end', [$team, $match, $running]) }}"
                      hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.confirm_end_match') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-square btn-ghost text-error"
                            aria-label="{{ __('kopling-sports-management::messages.end_match') }}" title="{{ __('kopling-sports-management::messages.end_match') }}">
                        <x-k::icon name="kopling-sports-management::stop" />
                    </button>
                </form>
            @elseif ($state === MatchState::Ended)
                <x-k::dropdown :label="__('kopling-sports-management::messages.more_actions')">
                    <x-slot:trigger>&vellip;</x-slot:trigger>
                    <li>
                        <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.periods.start', [$team, $match]) }}"
                              hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.confirm_resume_match') }}">
                            @csrf
                            <input type="hidden" name="type" value="{{ PeriodType::Play->value }}">
                            <button type="submit" class="w-full text-start">{{ __('kopling-sports-management::messages.resume_match') }}</button>
                        </form>
                    </li>
                </x-k::dropdown>
            @endif
        @endif
    </div>
</div>
