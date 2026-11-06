<li @if ($occurrence) id="occurrence-{{ $occurrence->id }}" @endif class="list-row scroll-mt-20 {{ $state && ! $state->acceptsEnlisting() && $state !== \Kopling\SportsManagement\TaskOccurrenceState::Full ? 'opacity-60' : '' }}">
    <div class="list-col-grow min-w-0 flex flex-col gap-2">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="font-semibold">
                    {{ $task->title }}
                    @if ($occurrence?->matchLabel())
                        <span class="font-normal opacity-60">&middot; {{ $occurrence->matchLabel() }}</span>
                    @endif
                </div>
                <div class="text-sm opacity-60">
                    {{ $when }}
                    @if ($task->peopleSummary())
                        &middot; {{ $task->peopleSummary() }}
                    @endif
                </div>
            </div>
            <span class="badge badge-sm shrink-0 whitespace-nowrap {{ $state?->badgeClass() ?? 'badge-ghost' }}" @if ($state) title="{{ $state->label() }}" @endif>
                {{ $task->max_people === null ? $count : $count.' / '.$task->max_people }}
                @if ($state && $state !== \Kopling\SportsManagement\TaskOccurrenceState::Open)
                    &middot; {{ $state->label() }}
                @endif
            </span>
        </div>

        @if ($enlistments->isNotEmpty())
            <ul class="flex flex-col gap-1">
                @foreach ($enlistments as $enlistment)
                    <li class="flex items-center gap-2 text-sm">
                        <span>{{ $enlistment->member->person->name }}</span>
                        @if ($enlistment->created_by)
                            <span class="badge badge-xs badge-outline" title="{{ $enlistment->creator?->name }}">{{ __('kopling-sports-management::messages.tasks.assigned') }}</span>
                        @endif
                        @if ($enlistment->note)
                            <span class="opacity-60 truncate">&middot; {{ $enlistment->note }}</span>
                        @endif
                        <form method="POST" action="{{ route('kopling-sports-management::sports-management/tasks.leave', [$team, $enlistment]) }}" class="ms-auto">
                            @csrf
                            <button type="submit" class="btn btn-xs btn-ghost">{{ __('kopling-sports-management::messages.remove') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($canEnlist && $players !== [])
            <form method="POST" action="{{ route('kopling-sports-management::sports-management/tasks.enlist', [$team, $task]) }}" class="join w-full" autocomplete="off">
                @csrf
                @if ($occurrence)
                    <input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">
                @endif
                <select name="team_member_id" class="select select-sm join-item w-auto" required aria-label="{{ __('kopling-sports-management::messages.tasks.assign_player') }}">
                    @foreach ($players as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <input type="text" name="note" maxlength="100" class="input input-sm join-item flex-1 min-w-0"
                       placeholder="{{ __('kopling-sports-management::messages.tasks.link.note_placeholder') }}"
                       aria-label="{{ __('kopling-sports-management::messages.tasks.link.note_placeholder') }}">
                <button type="submit" class="btn btn-sm join-item">{{ __('kopling-sports-management::messages.tasks.assign') }}</button>
            </form>
        @endif

        @if ($events->isNotEmpty())
            <details class="collapse collapse-arrow bg-base-200 text-sm">
                <summary class="collapse-title min-h-0 py-2">{{ trans_choice('kopling-sports-management::messages.tasks.log', $events->count()) }}</summary>
                <ul class="collapse-content flex flex-col gap-1">
                    @foreach ($events as $event)
                        <li class="opacity-80">
                            <span class="tabular-nums opacity-60">{{ $event->created_at->translatedFormat('j M H:i') }}</span>
                            &middot; {{ __('kopling-sports-management::messages.tasks.action.'.$event->action->value, ['name' => $event->member->person->name]) }}
                            &middot; {{ $event->creator ? __('kopling-sports-management::messages.tasks.by', ['name' => $event->creator->name]) : __('kopling-sports-management::messages.tasks.via_link') }}
                            @if ($event->note)
                                &middot; <span class="opacity-60">{{ $event->note }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</li>
