@php
    $approving = $team->tasks_approve_completion;
    $confirmUrl = route('kopling-sports-management::sports-management/tasks.confirm', $team);
@endphp
@forelse ($weeks as $weekStart => $occurrences)
    @php $weekPending = $occurrences->filter(fn ($occurrence) => $review->pending($occurrence) > 0); @endphp
    <section class="flex flex-col gap-2">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm opacity-60">{{ __('kopling-sports-management::messages.tasks.week', ['week' => \Carbon\CarbonImmutable::parse($weekStart)->isoWeek(), 'date' => \Carbon\CarbonImmutable::parse($weekStart)->translatedFormat('j M')]) }}</h2>
            @if ($approving && $weekPending->isNotEmpty())
                <form method="POST" action="{{ $confirmUrl }}">
                    @csrf
                    @foreach ($weekPending as $occurrence)
                        <input type="hidden" name="occurrence_ids[]" value="{{ $occurrence->id }}">
                    @endforeach
                    <button type="submit" class="btn btn-xs btn-success btn-soft">{{ __('kopling-sports-management::messages.tasks.all_done_week') }}</button>
                </form>
            @endif
        </div>
        <ul class="list card card-border bg-base-100">
            @foreach ($occurrences as $occurrence)
                @php
                    $pending = $review->pending($occurrence);
                    $enlistments = $review->enlistments($occurrence);
                @endphp
                <li class="list-row">
                    <div class="list-col-grow min-w-0 flex flex-col gap-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-semibold">
                                    {{ $occurrence->task->title }}
                                    @if ($occurrence->matchLabel())
                                        <span class="font-normal opacity-60">&middot; {{ $occurrence->matchLabel() }}</span>
                                    @endif
                                </div>
                                <div class="text-sm opacity-60">{{ $occurrence->startsAt()->translatedFormat($occurrence->time === null ? 'D j M' : 'D j M, H:i') }}</div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if ($approving && $pending > 0)
                                    <span class="badge badge-sm badge-warning whitespace-nowrap">{{ trans_choice('kopling-sports-management::messages.tasks.to_confirm', $pending) }}</span>
                                    <form method="POST" action="{{ $confirmUrl }}">
                                        @csrf
                                        <input type="hidden" name="occurrence_ids[]" value="{{ $occurrence->id }}">
                                        <button type="submit" class="btn btn-xs btn-success btn-soft">{{ __('kopling-sports-management::messages.tasks.all_done') }}</button>
                                    </form>
                                @else
                                    <span class="badge badge-sm badge-ghost">{{ \Kopling\SportsManagement\TaskOccurrenceState::Done->label() }}</span>
                                @endif
                            </div>
                        </div>

                        @if ($enlistments->isEmpty())
                            <p class="text-sm opacity-60">{{ __('kopling-sports-management::messages.tasks.nobody') }}</p>
                        @else
                            <ul class="flex flex-col gap-1">
                                @foreach ($enlistments as $enlistment)
                                    @php $outcome = $enlistment->outcomeFor($occurrence); @endphp
                                    <li class="flex flex-wrap items-center gap-2 text-sm">
                                        <span>{{ $enlistment->member->person->name }}</span>
                                        @if ($enlistment->isWholeTask())
                                            <span class="badge badge-xs badge-outline">{{ __('kopling-sports-management::messages.tasks.enrolment.whole_task') }}</span>
                                        @endif
                                        @if ($enlistment->note)
                                            <span class="opacity-60 truncate">&middot; {{ $enlistment->note }}</span>
                                        @endif
                                        <div class="flex items-center gap-1 ms-auto">
                                        @if ($approving)
                                            <div class="join">
                                                @foreach (\Kopling\SportsManagement\TaskOutcome::cases() as $option)
                                                    <form method="POST" action="{{ route('kopling-sports-management::sports-management/tasks.outcome', [$team, $enlistment]) }}" class="join-item">
                                                        @csrf
                                                        <input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">
                                                        <input type="hidden" name="outcome" value="{{ $option->value }}">
                                                        <button type="submit" aria-pressed="{{ $outcome === $option ? 'true' : 'false' }}"
                                                                class="btn btn-xs join-item {{ $outcome === $option ? ($option === \Kopling\SportsManagement\TaskOutcome::Done ? 'btn-success' : 'btn-error') : '' }}">{{ $option->label() }}</button>
                                                    </form>
                                                @endforeach
                                            </div>
                                        @endif
                                        @unless ($enlistment->isWholeTask())
                                            <form method="POST" action="{{ route('kopling-sports-management::sports-management/tasks.leave', [$team, $enlistment]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-ghost">{{ __('kopling-sports-management::messages.remove') }}</button>
                                            </form>
                                        @endunless
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($occurrence->task->enrolment === \Kopling\SportsManagement\TaskEnrolment::PerOccurrence && $players !== [])
                            <form method="POST" action="{{ route('kopling-sports-management::sports-management/tasks.enlist', [$team, $occurrence->task]) }}" class="join w-full" autocomplete="off">
                                @csrf
                                <input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">
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
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@empty
    <p class="opacity-60">{{ $review->pendingOnly ? __('kopling-sports-management::messages.tasks.nothing_to_confirm') : __('kopling-sports-management::messages.tasks.nothing_past') }}</p>
@endforelse
