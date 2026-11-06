@extends('kopling-sports-management::layouts.sports-management')
@section('title', __('kopling-sports-management::messages.tasks.balance').' · '.$team->name)
@php
    $most = max(1, $rows->max(fn ($row) => $row['points'] + $row['planned']) ?? 0);
    $duration = fn (int $minutes) => \Carbon\CarbonInterval::minutes($minutes)->cascade()->forHumans(['short' => true]);
    $money = fn (int $cents) => \Illuminate\Support\Number::currency($cents / 100, 'EUR', app()->getLocale());
@endphp

@section('content')
    <div class="max-w-4xl flex flex-col gap-6">
        <div class="flex flex-col gap-3">
            <a href="{{ route('kopling-sports-management::sports-management/teams.show', $team) }}" class="link link-hover text-sm opacity-60 self-start">
                {{ __('kopling-sports-management::messages.tasks.back_to_team', ['team' => $team->name]) }}
            </a>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold">{{ __('kopling-sports-management::messages.tasks.balance') }}</h1>
                <a href="{{ route('kopling-sports-management::sports-management/tasks.index', $team) }}" class="btn btn-sm btn-ghost">{{ __('kopling-sports-management::messages.tasks.planning') }}</a>
            </div>
            <p class="text-sm opacity-60">{{ __('kopling-sports-management::messages.tasks.balance_help') }}</p>
        </div>

        @if ($rows->isEmpty())
            <p class="opacity-60">{{ __('kopling-sports-management::messages.no_members') }}</p>
        @else
            @foreach ($rows->groupBy(fn ($row) => $row['left'] ? 'left' : 'current')->sortKeys() as $section => $group)
                @if ($section === 'left')
                    <h2 class="text-sm opacity-60 mt-2">{{ __('kopling-sports-management::messages.tasks.former_players') }}</h2>
                @endif
            <div class="join join-vertical w-full bg-base-100">
                @foreach ($group as $row)
                    <details class="collapse collapse-arrow join-item border border-base-300">
                        <summary class="collapse-title grid grid-cols-[minmax(0,1fr)_auto] sm:grid-cols-[minmax(0,12rem)_1fr_auto] items-center gap-x-4 gap-y-1">
                            <span class="truncate font-semibold">{{ $row['member']->person->name }}</span>
                            {{-- Stacked earned + planned: daisyUI's progress has one value only. --}}
                            <div class="hidden sm:flex h-2 rounded-full bg-base-300 overflow-hidden" role="img"
                                 aria-label="{{ trans_choice('kopling-sports-management::messages.tasks.points', $row['points']) }}, {{ trans_choice('kopling-sports-management::messages.tasks.planned_points', $row['planned']) }}">
                                <div class="{{ $row['points'] === 0 ? 'bg-warning' : 'bg-primary' }}" style="width: {{ $row['points'] / $most * 100 }}%"></div>
                                <div class="bg-primary/30" style="width: {{ $row['planned'] / $most * 100 }}%"></div>
                            </div>
                            <span class="text-sm tabular-nums whitespace-nowrap text-end">
                                <span class="font-semibold">{{ trans_choice('kopling-sports-management::messages.tasks.points', $row['points']) }}</span>
                                @if ($row['planned'] > 0)
                                    <span class="text-primary/70">+{{ trans_choice('kopling-sports-management::messages.tasks.planned_points', $row['planned']) }}</span>
                                @endif
                                <span class="opacity-60">&middot; {{ __('kopling-sports-management::messages.tasks.done_upcoming', ['done' => $row['done'], 'upcoming' => $row['upcoming']]) }}</span>
                                @if ($row['no_shows'] > 0)
                                    <span class="badge badge-sm badge-error">{{ trans_choice('kopling-sports-management::messages.tasks.no_shows', $row['no_shows']) }}</span>
                                @endif
                                @if ($row['pending'] > 0)
                                    <span class="badge badge-sm badge-warning">{{ trans_choice('kopling-sports-management::messages.tasks.to_confirm', $row['pending']) }}</span>
                                @endif
                            </span>
                        </summary>
                        <div class="collapse-content flex flex-col gap-2 text-sm">
                            @if ($row['minutes'] > 0 || $row['cents'] > 0)
                                <p class="opacity-60">
                                    {{ collect([
                                        $row['minutes'] > 0 ? __('kopling-sports-management::messages.tasks.effort_time', ['duration' => $duration($row['minutes'])]) : null,
                                        $row['cents'] > 0 ? __('kopling-sports-management::messages.tasks.effort_costs', ['amount' => $money($row['cents'])]) : null,
                                    ])->filter()->join(' · ') }}
                                </p>
                            @endif
                            @forelse ($row['items'] as $item)
                                <div class="flex items-baseline gap-2">
                                    <span class="w-24 shrink-0 opacity-60 tabular-nums">
                                        {{ $item['whole'] ? __('kopling-sports-management::messages.tasks.enrolment.whole_task') : $item['date']->translatedFormat('D j M') }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        {{ $item['title'] }}
                                        @if ($item['note'])
                                            <span class="opacity-60">&middot; {{ $item['note'] }}</span>
                                        @endif
                                        @if ($item['whole'])
                                            <span class="opacity-60">&middot; {{ __('kopling-sports-management::messages.tasks.done_upcoming', ['done' => $item['done'], 'upcoming' => $item['upcoming']]) }}</span>
                                            @if ($item['no_shows'] > 0)
                                                <span class="text-error">&middot; {{ trans_choice('kopling-sports-management::messages.tasks.no_shows', $item['no_shows']) }}</span>
                                            @endif
                                            @if ($item['ended_on'])
                                                <span class="opacity-60">&middot; {{ __('kopling-sports-management::messages.tasks.ended_on', ['date' => $item['ended_on']->translatedFormat('j M')]) }}</span>
                                            @endif
                                        @endif
                                    </span>
                                    <span class="shrink-0 tabular-nums">
                                        @if ($item['done'] > 0)
                                            +{{ $item['points'] }}
                                        @elseif (! $item['whole'] && $item['no_shows'] > 0)
                                            <span class="text-error">{{ \Kopling\SportsManagement\TaskOutcome::NoShow->label() }}</span>
                                        @elseif (! $item['whole'] && $item['pending'] > 0)
                                            <span class="text-warning">{{ trans_choice('kopling-sports-management::messages.tasks.to_confirm', 1) }}</span>
                                        @else
                                            <span class="opacity-60">{{ __('kopling-sports-management::messages.tasks.upcoming_item') }}</span>
                                        @endif
                                    </span>
                                </div>
                            @empty
                                <p class="opacity-60">{{ __('kopling-sports-management::messages.tasks.nothing_yet') }}</p>
                            @endforelse
                        </div>
                    </details>
                @endforeach
            </div>
            @endforeach
        @endif
    </div>
@endsection
