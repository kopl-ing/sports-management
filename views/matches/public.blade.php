@extends('kopling-sports-management::layouts.live')
@use('Kopling\SportsManagement\HomeAway')
@use('Kopling\SportsManagement\MatchState')

@section('content')
    <div id="sm-live" class="flex flex-col gap-6"
         @if ($pollSeconds) hx-get="{{ $url }}" hx-trigger="every {{ $pollSeconds }}s" hx-select="#sm-live" hx-swap="outerHTML" @endif>
        <section class="card card-border bg-base-100">
            <div class="card-body items-center text-center gap-2">
                <span class="badge {{ $state === MatchState::Live ? 'badge-error' : 'badge-ghost' }}">{{ $state->label() }}</span>
                <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4 w-full">
                    <h1 class="font-semibold text-end">{{ $homeAway === HomeAway::Home ? $teamName : $opponent }}</h1>
                    <span class="text-4xl font-bold tabular-nums whitespace-nowrap">
                        @if ($state === MatchState::Planned)
                            &ndash;
                        @elseif ($homeAway === HomeAway::Home)
                            {{ $score['us'] }} &ndash; {{ $score['them'] }}
                        @else
                            {{ $score['them'] }} &ndash; {{ $score['us'] }}
                        @endif
                    </span>
                    <h1 class="font-semibold text-start">{{ $homeAway === HomeAway::Home ? $opponent : $teamName }}</h1>
                </div>
                @if ($running)
                    <p class="text-sm">
                        {{ $running['label'] }} &middot;
                        @include('kopling-sports-management::matches.tracking.clock', ['seconds' => $running['seconds'], 'ticking' => $running['ticking']])
                    </p>
                @endif
                <p class="text-sm opacity-60">{{ $scheduledAt->translatedFormat('l j F Y, H:i') }}</p>
                @if ($location)
                    <p class="text-sm opacity-60 whitespace-pre-line">{{ $location }}</p>
                @endif
            </div>
        </section>

        @if ($field)
            <section class="flex flex-col gap-2">
                <h2 class="text-sm opacity-60">{{ $state === MatchState::Planned ? __('kopling-sports-management::messages.lineup') : $sport->trans('on_field') }}</h2>
                <div class="card card-border bg-base-100 overflow-hidden">
                    @foreach ($field as $row)
                        <div class="relative flex flex-wrap items-center justify-center gap-4 p-3 min-h-16 border-b border-dashed border-base-300 last:border-b-0">
                            <span class="absolute start-3 top-2 text-xs font-semibold opacity-40" title="{{ $row['zone']->label($sport) }}">{{ $row['zone']->value }}</span>
                            @foreach ($row['players'] as $player)
                                <x-k::person.avatar :name="$player['initials']" :initials="$player['initials']" :color="$player['color']" :mask="null" size="w-12" />
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($state !== MatchState::Planned)
            <section class="flex flex-col gap-2">
                <h2 class="text-sm opacity-60">{{ __('kopling-sports-management::messages.events') }}</h2>
                <ul class="list card card-border bg-base-100">
                    @forelse ($goals as $goal)
                        <li class="list-row items-center">
                            <span class="w-8 text-sm opacity-60 tabular-nums">{{ $goal['minute'] }}'</span>
                            <span class="badge badge-sm {{ $goal['opponent'] ? 'badge-error' : 'badge-success' }}">{{ $multiplePoints ? '+'.$goal['points'] : $sport->trans('goal') }}</span>
                            <span class="list-col-grow flex items-center gap-2">
                                @if ($goal['opponent'])
                                    {{ $opponent }}
                                @elseif ($goal['own_goal'])
                                    {{ __('kopling-sports-management::messages.own_goal_by', ['opponent' => $opponent]) }}
                                @else
                                    @if ($goal['scorer'])
                                        <x-k::person.avatar :name="$goal['scorer']['initials']" :initials="$goal['scorer']['initials']" :color="$goal['scorer']['color']" :mask="null" size="w-8" />
                                    @endif
                                    @if ($goal['assist'])
                                        <span class="text-sm opacity-60">{{ __('kopling-sports-management::messages.assist') }}</span>
                                        <x-k::person.avatar :name="$goal['assist']['initials']" :initials="$goal['assist']['initials']" :color="$goal['assist']['color']" :mask="null" size="w-6" />
                                    @endif
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="list-row opacity-60">{{ $sport->trans('no_goals') }}</li>
                    @endforelse
                </ul>
            </section>
        @endif

        @if ($pollSeconds)
            <p class="text-xs opacity-40 text-center">{{ __('kopling-sports-management::messages.public.updates') }}</p>
        @endif
    </div>
@endsection
