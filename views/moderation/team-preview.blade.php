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
</div>
