@extends('kopling-sports-management::layouts.sports-management')
@section('title', __('kopling-sports-management::messages.tasks.planning').' · '.$team->name)

@section('content')
    <div class="max-w-4xl flex flex-col gap-6">
        <div class="flex flex-col gap-3">
            <a href="{{ route('kopling-sports-management::sports-management/teams.show', $team) }}" class="link link-hover text-sm opacity-60 self-start">
                {{ __('kopling-sports-management::messages.tasks.back_to_team', ['team' => $team->name]) }}
            </a>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold">{{ __('kopling-sports-management::messages.tasks.planning') }}</h1>
                <div role="tablist" class="tabs tabs-box tabs-sm">
                    @foreach (array_filter([null => true, 'open' => true, 'past' => true, 'confirm' => $team->tasks_approve_completion]) as $key => $shown)
                        <a role="tab" href="{{ route('kopling-sports-management::sports-management/tasks.index', ['team' => $team] + ($key === '' ? [] : ['filter' => $key])) }}"
                           class="tab {{ ($filter ?? '') === $key ? 'tab-active' : '' }}">{{ __('kopling-sports-management::messages.tasks.filter.'.($key === '' ? 'all' : $key)) }}</a>
                    @endforeach
                </div>
            </div>
        </div>

        <div id="sm-task-planning" class="flex flex-col gap-6"
             hx-boost:inherited="true" hx-target:inherited="#sm-task-planning" hx-select:inherited="#sm-task-planning" hx-swap:inherited="outerHTML">
            @error('enlist')
                <div role="alert" class="alert alert-error alert-soft">{{ $message }}</div>
            @enderror

            @isset($review)
                @include('kopling-sports-management::tasks.review')
            @else
            @if ($wholeTasks->isNotEmpty())
                <section class="flex flex-col gap-2">
                    <h2 class="text-sm opacity-60">{{ __('kopling-sports-management::messages.tasks.link.whole_tasks') }}</h2>
                    <ul class="list card card-border bg-base-100">
                        @foreach ($wholeTasks as $task)
                            @php $enlistments = $board->activeEnlistments($task); @endphp
                            @include('kopling-sports-management::tasks.staff-row', [
                                'task' => $task,
                                'occurrence' => null,
                                'when' => $task->scheduleSummary(),
                                'state' => null,
                                'count' => $enlistments->count(),
                                'enlistments' => $enlistments,
                                'skipped' => collect(),
                                'canEnlist' => $board->wholeTaskAcceptsEnlisting($task),
                                'events' => $task->events,
                            ])
                        @endforeach
                    </ul>
                </section>
            @endif

            @forelse ($weeks as $weekStart => $occurrences)
                <section class="flex flex-col gap-2">
                    <h2 class="text-sm opacity-60">{{ __('kopling-sports-management::messages.tasks.week', ['week' => \Carbon\CarbonImmutable::parse($weekStart)->isoWeek(), 'date' => \Carbon\CarbonImmutable::parse($weekStart)->translatedFormat('j M')]) }}</h2>
                    <ul class="list card card-border bg-base-100">
                        @foreach ($occurrences as $occurrence)
                            @php $state = $board->state($occurrence); @endphp
                            @include('kopling-sports-management::tasks.staff-row', [
                                'task' => $occurrence->task,
                                'occurrence' => $occurrence,
                                'when' => $occurrence->startsAt()->translatedFormat($occurrence->time === null ? 'D j M' : 'D j M, H:i'),
                                'state' => $state,
                                'count' => $board->count($occurrence),
                                'enlistments' => $board->enlistmentsFor($occurrence),
                                'skipped' => $board->skippedFor($occurrence),
                                'canEnlist' => $state->acceptsEnlisting(),
                                'events' => $occurrence->events,
                            ])
                        @endforeach
                    </ul>
                </section>
            @empty
                @if ($wholeTasks->isEmpty())
                    <p class="opacity-60">{{ __('kopling-sports-management::messages.tasks.link.nothing') }}</p>
                @endif
            @endforelse
            @endisset

            @if ($laterUrl)
                <a href="{{ $laterUrl }}" class="btn btn-ghost self-center">{{ isset($review) ? __('kopling-sports-management::messages.tasks.earlier') : __('kopling-sports-management::messages.tasks.link.later') }}</a>
            @endif
        </div>
    </div>
@endsection
