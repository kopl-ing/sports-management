<section class="card card-border bg-base-100">
    <div class="card-body gap-3">
        <div class="flex items-center justify-between gap-2">
            <h2 class="card-title">{{ __('kopling-sports-management::messages.tasks.heading') }}</h2>

            @if ($canManageTeam)
                <a href="{{ route('kopling-sports-management::sports-management/tasks.index', $team) }}" class="btn btn-sm btn-ghost ms-auto">{{ __('kopling-sports-management::messages.tasks.planning') }}</a>
                <x-k::modal :label="__('kopling-sports-management::messages.tasks.add')" id="modal-task-create" :open="$duplicate !== null">
                    <x-slot:trigger>{{ __('kopling-sports-management::messages.tasks.add') }}</x-slot:trigger>
                    @include('kopling-sports-management::tasks.form', [
                        'action' => route('kopling-sports-management::sports-management/tasks.store', $team),
                        'formId' => 'modal-task-create',
                        'title' => $duplicate ? __('kopling-sports-management::messages.tasks.duplicate_title') : __('kopling-sports-management::messages.tasks.add'),
                        'task' => $duplicate,
                    ])
                </x-k::modal>
            @endif
        </div>

        @if ($canManageTeam && $tasks->isNotEmpty() && $team->tasks_link_mode === \Kopling\SportsManagement\TaskLinkMode::Team)
            <div class="flex flex-col gap-2">
                <span class="text-sm opacity-60">{{ __('kopling-sports-management::messages.tasks.link.label') }}</span>
                <div class="flex gap-2">
                    @include('kopling-sports-management::tasks.copy-link', ['url' => $team->tasksUrl()])
                    <form method="POST" action="{{ route('kopling-sports-management::sports-management/tasks.link', $team) }}"
                          hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.tasks.link.confirm_regenerate') }}">
                        @csrf
                        <button type="submit" class="btn">{{ __('kopling-sports-management::messages.tasks.link.regenerate') }}</button>
                    </form>
                </div>
            </div>
        @elseif ($canManageTeam && $tasks->isNotEmpty())
            <p class="text-sm opacity-60">{{ __('kopling-sports-management::messages.tasks.link.per_player_note') }}</p>
        @endif

        @if ($tasks->isEmpty())
            <p class="opacity-60">{{ __('kopling-sports-management::messages.tasks.none') }}</p>
        @else
            <ul class="list">
                @foreach ($tasks as $task)
                    @php
                        $modalId = 'modal-task-edit-'.$task->id;
                        $next = $task->upcomingOccurrences->first();
                        $people = $task->peopleSummary();
                    @endphp
                    <li class="list-row items-center">
                        <div class="list-col-grow min-w-0">
                            <div class="truncate font-semibold">{{ $task->title }}</div>
                            <div class="text-sm opacity-60">
                                {{ $task->scheduleSummary() }}
                                @if ($task->durationSummary())
                                    &middot; {{ $task->durationSummary() }}
                                @endif
                                @if ($task->enrolment === \Kopling\SportsManagement\TaskEnrolment::WholeTask)
                                    &middot; {{ $task->enrolment->label() }}
                                @endif
                            </div>
                            <div class="text-sm opacity-60">
                                @if ($next)
                                    {{ __('kopling-sports-management::messages.tasks.next', ['date' => $next->startsAt()->translatedFormat($next->time === null ? 'D j M' : 'D j M, H:i')]) }}
                                    &middot; {{ trans_choice('kopling-sports-management::messages.tasks.upcoming_count', $task->upcomingOccurrences->count()) }}
                                @else
                                    {{ __('kopling-sports-management::messages.tasks.no_upcoming') }}
                                @endif
                            </div>
                        </div>
                        @if ($people)
                            <span class="badge badge-sm badge-outline shrink-0 whitespace-nowrap">{{ $people }}</span>
                        @endif
                        @if ($canManageTeam)
                            <x-k::modal :label="__('kopling-sports-management::messages.edit')" :id="$modalId">
                                <x-slot:trigger>{{ __('kopling-sports-management::messages.edit') }}</x-slot:trigger>
                                @include('kopling-sports-management::tasks.form', [
                                    'action' => route('kopling-sports-management::sports-management/tasks.update', [$team, $task]),
                                    'formId' => $modalId,
                                    'title' => $task->title,
                                    'task' => $task,
                                ])
                                <div class="flex flex-wrap gap-2 mt-6 pt-4 border-t border-base-300">
                                    <a href="{{ route('kopling-sports-management::sports-management/teams.show', ['team' => $team, 'duplicate' => $task->id]) }}" class="btn btn-sm">{{ __('kopling-sports-management::messages.tasks.duplicate') }}</a>
                                    <form method="POST" action="{{ route('kopling-sports-management::sports-management/tasks.destroy', [$team, $task]) }}"
                                          hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.tasks.confirm_delete') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-sports-management::messages.delete') }}</button>
                                    </form>
                                </div>
                            </x-k::modal>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
