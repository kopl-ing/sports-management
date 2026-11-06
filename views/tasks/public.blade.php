@extends('kopling-sports-management::layouts.live')
@use('Kopling\SportsManagement\TaskOccurrenceState')
@section('title', $player['name'].' · '.__('kopling-sports-management::messages.tasks.heading').' · '.$teamName)

@section('content')
    <div class="flex flex-col gap-6">
        <section class="card card-border bg-base-100">
            <div class="card-body flex-row items-center gap-4">
                <x-k::person.avatar :name="$player['name']" :color="$player['color']" :mask="null" size="w-14" />
                <div class="min-w-0 flex-1">
                    <h1 class="text-xl font-semibold truncate">{{ $player['name'] }}</h1>
                    <p class="text-sm opacity-60 truncate">{{ $teamName }}</p>
                </div>
                @if ($pickUrl)
                    <a href="{{ $pickUrl }}" class="link link-hover text-sm opacity-60 shrink-0">{{ __('kopling-sports-management::messages.tasks.link.not_you') }}</a>
                @endif
            </div>
        </section>

        <div id="sm-tasks" class="flex flex-col gap-6"
             hx-boost:inherited="true" hx-target:inherited="#sm-tasks" hx-select:inherited="#sm-tasks" hx-swap:inherited="outerHTML">
            @error('enlist')
                <div role="alert" class="alert alert-error alert-soft">{{ $message }}</div>
            @enderror

            @if ($needsPeople !== [])
                <div role="alert" class="alert alert-warning alert-soft flex-col items-stretch gap-1">
                    <span class="font-semibold">{{ __('kopling-sports-management::messages.tasks.soon.heading') }}</span>
                    <ul class="text-sm">
                        @foreach ($needsPeople as $row)
                            <li>
                                <a href="#occurrence-{{ $row['id'] }}" class="link link-hover">
                                    {{ $row['date']->translatedFormat('D j M') }}@if ($row['time']), {{ $row['time'] }}@endif
                                    &middot; {{ $row['title'] }}
                                    @if ($row['match'])
                                        ({{ $row['match'] }})
                                    @endif
                                    &middot; {{ __('kopling-sports-management::messages.tasks.soon.count', ['count' => $row['count'], 'min' => $row['min']]) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($wholeTasks !== [])
                <section class="flex flex-col gap-2">
                    <h2 class="text-sm opacity-60">{{ __('kopling-sports-management::messages.tasks.link.whole_tasks') }}</h2>
                    <ul class="list card card-border bg-base-100">
                        @foreach ($wholeTasks as $row)
                            @include('kopling-sports-management::tasks.public-row', ['when' => $row['schedule'], 'occurrenceId' => null, 'state' => null])
                        @endforeach
                    </ul>
                </section>
            @endif

            @forelse ($days as $date => $rows)
                <section class="flex flex-col gap-2">
                    <h2 class="text-sm opacity-60">{{ $rows[0]['date']->translatedFormat('l j F') }}</h2>
                    <ul class="list card card-border bg-base-100">
                        @foreach ($rows as $row)
                            @include('kopling-sports-management::tasks.public-row', ['when' => $row['time'], 'occurrenceId' => $row['id'], 'state' => $row['state']])
                        @endforeach
                    </ul>
                </section>
            @empty
                @if ($wholeTasks === [])
                    <p class="opacity-60 text-center">{{ __('kopling-sports-management::messages.tasks.link.nothing') }}</p>
                @endif
            @endforelse

            @if ($laterUrl)
                <a href="{{ $laterUrl }}" class="btn btn-ghost self-center">{{ __('kopling-sports-management::messages.tasks.link.later') }}</a>
            @endif

            @if ($balance)
                <section class="flex flex-col gap-2">
                    <h2 class="text-sm opacity-60">{{ __('kopling-sports-management::messages.tasks.balance') }}</h2>
                    <ul class="list card card-border bg-base-100">
                        @foreach ($balance as $row)
                            <li class="list-row items-center">
                                <x-k::person.avatar :name="$row['name']" :color="$row['color']" :mask="null" size="w-6" />
                                <span class="list-col-grow truncate {{ $row['mine'] ? 'font-semibold' : '' }}">{{ $row['name'] }}</span>
                                <span class="text-sm tabular-nums whitespace-nowrap">
                                    <span class="font-semibold">{{ trans_choice('kopling-sports-management::messages.tasks.points', $row['points']) }}</span>
                                    <span class="opacity-60">&middot; {{ __('kopling-sports-management::messages.tasks.done_upcoming', ['done' => $row['done'], 'upcoming' => $row['upcoming']]) }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>

    @if ($storageKey)
        <div hidden x-data="{ init() { try { localStorage.setItem(@js($storageKey), @js($url)) } catch (e) {} } }"></div>
    @endif
@endsection
