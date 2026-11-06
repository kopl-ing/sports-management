{{-- A reported team's queue preview: the only place moderators see roster names and match details. --}}
@php($team = $flaggable)
<div class="flex flex-col gap-3">
    @include('kopling-sports-management::moderation.team-summary', ['sanctionable' => true])

    <details class="collapse collapse-arrow border border-base-300 bg-base-100">
        <summary class="collapse-title text-sm font-semibold">{{ __('kopling-sports-management::messages.roster') }}</summary>
        <div class="collapse-content text-sm">
            <ul class="flex flex-col gap-1">
                @forelse (\Kopling\SportsManagement\TeamMember::sorted($team->members()->with('person')->get()) as $member)
                    <li>
                        {{ $member->person->name }}
                        @if ($member->guest)
                            <span class="badge badge-xs badge-outline">{{ __('kopling-sports-management::messages.guest') }}</span>
                        @endif
                    </li>
                @empty
                    <li class="opacity-60">{{ __('kopling-sports-management::messages.no_members') }}</li>
                @endforelse
            </ul>
        </div>
    </details>

    <details class="collapse collapse-arrow border border-base-300 bg-base-100">
        <summary class="collapse-title text-sm font-semibold">{{ __('kopling-sports-management::messages.matches') }}</summary>
        <div class="collapse-content text-sm">
            <ul class="flex flex-col gap-1">
                @forelse ($team->matches()->orderByDesc('scheduled_at')->get() as $match)
                    <li>
                        {{ $match->scheduled_at->translatedFormat('j M Y, H:i') }} &middot; {{ $match->opponent_name }}
                        &middot; {{ $match->home_away->label() }}
                        @if ($match->location_address)
                            <span class="opacity-60">&middot; {{ $match->location_address }}</span>
                        @endif
                    </li>
                @empty
                    <li class="opacity-60">{{ __('kopling-sports-management::messages.no_matches') }}</li>
                @endforelse
            </ul>
        </div>
    </details>

    @php
        $tasks = $team->tasks()->with(['enlistments' => fn ($query) => $query->whereNotNull('note')->latest()->with(['member.person', 'occurrence'])])->orderBy('title')->get();
        $periods = $team->periods()->get();
    @endphp
    <details class="collapse collapse-arrow border border-base-300 bg-base-100">
        <summary class="collapse-title text-sm font-semibold">{{ __('kopling-sports-management::messages.tasks.heading') }}</summary>
        <div class="collapse-content text-sm flex flex-col gap-3">
            @forelse ($tasks as $task)
                <div>
                    <div class="font-semibold">{{ $task->title }} <span class="font-normal opacity-60">&middot; {{ $task->scheduleSummary() }}</span></div>
                    @if ($task->description)
                        <p class="whitespace-pre-line opacity-80">{{ $task->description }}</p>
                    @endif
                    @if ($task->enlistments->isNotEmpty())
                        <ul class="flex flex-col gap-1 mt-1 ps-3 border-s border-base-300">
                            @foreach ($task->enlistments as $enlistment)
                                <li>
                                    <span class="opacity-60">{{ $enlistment->occurrence?->date->translatedFormat('j M') ?? __('kopling-sports-management::messages.tasks.enrolment.whole_task') }} &middot; {{ $enlistment->member->person->name }}:</span>
                                    {{ $enlistment->note }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @empty
                <p class="opacity-60">{{ __('kopling-sports-management::messages.tasks.none') }}</p>
            @endforelse
            @if ($periods->isNotEmpty())
                <div>
                    <div class="font-semibold">{{ __('kopling-sports-management::messages.periods.heading') }}</div>
                    <p class="opacity-80">{{ $periods->map(fn ($period) => $period->name.' ('.$period->starts_on->translatedFormat('j M Y').' – '.$period->ends_on->translatedFormat('j M Y').')')->join(', ') }}</p>
                </div>
            @endif
        </div>
    </details>
</div>
