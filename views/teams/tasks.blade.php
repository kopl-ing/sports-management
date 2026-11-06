<section class="card card-border bg-base-100">
    <div class="card-body gap-3">
        <div class="flex items-center justify-between">
            <h2 class="card-title">{{ __('kopling-sports-management::messages.tasks.heading') }}</h2>

            @if ($canManageTeam)
                <x-k::modal :label="__('kopling-sports-management::messages.tasks.add')" id="modal-task-create">
                    <x-slot:trigger>{{ __('kopling-sports-management::messages.tasks.add') }}</x-slot:trigger>
                    @include('kopling-sports-management::tasks.form', [
                        'action' => route('kopling-sports-management::sports-management/tasks.store', $team),
                        'formId' => 'modal-task-create',
                        'title' => __('kopling-sports-management::messages.tasks.add'),
                        'task' => null,
                    ])
                </x-k::modal>
            @endif
        </div>

        @if ($tasks->isEmpty())
            <p class="opacity-60">{{ __('kopling-sports-management::messages.tasks.none') }}</p>
        @else
            <ul class="list">
                @foreach ($tasks as $task)
                    @php
                        $modalId = 'modal-task-edit-'.$task->id;
                        $next = $task->upcomingOccurrences->first();
                        $people = match (true) {
                            $task->min_people !== null && $task->max_people !== null => __('kopling-sports-management::messages.tasks.people_range', ['min' => $task->min_people, 'max' => $task->max_people]),
                            $task->min_people !== null => __('kopling-sports-management::messages.tasks.people_min', ['min' => $task->min_people]),
                            $task->max_people !== null => __('kopling-sports-management::messages.tasks.people_max', ['max' => $task->max_people]),
                            default => null,
                        };
                    @endphp
                    <li class="list-row items-center">
                        <div class="list-col-grow min-w-0">
                            <div class="truncate font-semibold">{{ $task->title }}</div>
                            <div class="text-sm opacity-60">
                                {{ $task->scheduleSummary() }}
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
                                <form method="POST" action="{{ route('kopling-sports-management::sports-management/tasks.destroy', [$team, $task]) }}"
                                      hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.tasks.confirm_delete') }}"
                                      class="mt-6 pt-4 border-t border-base-300">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-sports-management::messages.delete') }}</button>
                                </form>
                            </x-k::modal>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
