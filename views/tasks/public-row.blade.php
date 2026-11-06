@php
    $closed = $state === \Kopling\SportsManagement\TaskOccurrenceState::Cancelled || $state === \Kopling\SportsManagement\TaskOccurrenceState::Done;
    $modalId = 'modal-enlist-'.($occurrenceId ?? $row['id']);
@endphp
<li @if ($occurrenceId) id="occurrence-{{ $occurrenceId }}" @endif class="list-row items-center scroll-mt-20 {{ $closed ? 'opacity-60' : '' }}">
    <div class="list-col-grow min-w-0">
        <div class="font-semibold truncate">{{ $row['title'] }}</div>
        @if ($row['description'])
            <p class="text-sm opacity-60 truncate">{{ $row['description'] }}</p>
        @endif
        @if ($when || $row['match'])
            <div class="text-sm opacity-60">{{ collect([$when, $row['match']])->filter()->join(' · ') }}</div>
        @endif
        @if ($row['people'] !== [] || $row['skipped'] !== [])
            <ul class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                @foreach ($row['people'] as $person)
                    <li class="flex items-center gap-1 text-sm">
                        <x-k::person.avatar :name="$person['name']" :color="$person['color']" :mask="null" size="w-5" />
                        <span class="{{ $person['mine'] ? 'font-semibold' : 'opacity-80' }}">{{ $person['name'] }}</span>
                        @if ($person['regular'])
                            <span class="badge badge-xs badge-ghost">{{ __('kopling-sports-management::messages.tasks.regular') }}</span>
                        @endif
                        @if ($person['leave'])
                            <form method="POST" action="{{ $leaveUrl($person['leave']) }}">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-ghost">{{ __('kopling-sports-management::messages.tasks.link.leave') }}</button>
                            </form>
                        @elseif ($person['skip'])
                            <form method="POST" action="{{ $dateUrl($person['skip']) }}">
                                @csrf
                                <input type="hidden" name="occurrence_id" value="{{ $occurrenceId }}">
                                <input type="hidden" name="action" value="skip">
                                <button type="submit" class="btn btn-xs btn-ghost">{{ __('kopling-sports-management::messages.tasks.link.skip') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
                @foreach ($row['skipped'] as $person)
                    <li class="flex items-center gap-1 text-sm">
                        <x-k::person.avatar :name="$person['name']" :color="$person['color']" :mask="null" size="w-5" />
                        <span class="line-through opacity-60">{{ $person['name'] }}</span>
                        @if ($person['restore'])
                            <form method="POST" action="{{ $dateUrl($person['restore']) }}">
                                @csrf
                                <input type="hidden" name="occurrence_id" value="{{ $occurrenceId }}">
                                <input type="hidden" name="action" value="restore">
                                <button type="submit" class="btn btn-xs btn-ghost">{{ __('kopling-sports-management::messages.tasks.link.restore') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <span class="badge badge-sm shrink-0 whitespace-nowrap {{ $row['badge'] }}">
        @if ($state && ! $state->acceptsEnlisting() && $state !== \Kopling\SportsManagement\TaskOccurrenceState::Full)
            {{ $state->label() }}
        @else
            {{ $row['max'] === null ? $row['count'] : $row['count'].' / '.$row['max'] }}
        @endif
    </span>
    @if ($row['staff_only'])
        <span class="text-xs opacity-60 shrink-0 max-w-24 text-end">{{ __('kopling-sports-management::messages.tasks.staff_only_note') }}</span>
    @elseif ($row['can_enlist'])
        <x-k::modal :label="$row['title']" :id="$modalId">
            <x-slot:trigger class="btn btn-sm btn-primary">{{ __('kopling-sports-management::messages.tasks.link.enlist') }}</x-slot:trigger>
            <form method="POST" action="{{ $enlistUrl }}" class="flex flex-col gap-4" autocomplete="off">
                @csrf
                <input type="hidden" name="task_id" value="{{ $row['task_id'] ?? $row['id'] }}">
                @if ($occurrenceId)
                    <input type="hidden" name="occurrence_id" value="{{ $occurrenceId }}">
                @endif
                <div>
                    <h2 class="text-lg font-semibold">{{ $row['title'] }}</h2>
                    <p class="text-sm opacity-60">{{ collect([$row['when'], $row['match']])->filter()->join(' · ') }}</p>
                    @if ($row['details'] !== '')
                        <p class="text-sm opacity-60">{{ $row['details'] }}</p>
                    @endif
                </div>
                @if ($row['description'])
                    <p class="whitespace-pre-line">{{ $row['description'] }}</p>
                @endif
                @if ($row['people'] !== [])
                    <div class="flex flex-col gap-1">
                        <span class="text-sm opacity-60">{{ __('kopling-sports-management::messages.tasks.link.signed_up') }}</span>
                        <ul class="flex flex-col gap-1">
                            @foreach ($row['people'] as $person)
                                <li class="flex items-center gap-2 text-sm">
                                    <x-k::person.avatar :name="$person['name']" :color="$person['color']" :mask="null" size="w-6" />
                                    <span>{{ $person['name'] }}</span>
                                    @if ($person['note'])
                                        <span class="opacity-60">&middot; {{ $person['note'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div role="alert" class="alert alert-info alert-soft text-sm">{{ $row['undo'] }}</div>
                <x-k::form.input :data="['name' => 'note', 'label' => __('kopling-sports-management::messages.tasks.link.note_label'), 'description' => __('kopling-sports-management::messages.tasks.link.note_help')]" />
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('kopling-sports-management::messages.tasks.link.enlist_as', ['name' => $player['name']]) }}</button>
                    <x-k::modal.cancel />
                </div>
            </form>
        </x-k::modal>
    @endif
</li>
