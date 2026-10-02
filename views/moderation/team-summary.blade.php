<div class="flex flex-wrap items-start justify-between gap-2">
    <div class="min-w-0">
        <p class="font-semibold truncate">{{ $team->name }}</p>
        <p class="text-sm opacity-60">{{ $team->club }} &middot; {{ $team->season }}
            @if ($team->formatPreset)
                &middot; {{ $team->formatPreset->name }}
            @endif
        </p>
    </div>
    <div class="flex flex-wrap gap-1">
        @if ($team->trashed())
            <span class="badge badge-sm badge-error badge-outline">{{ __('kopling-moderation::moderation.hidden') }}</span>
        @endif
        <span class="badge badge-sm">{{ trans_choice('kopling-sports-management::messages.player_count', $team->members_count ?? $team->members()->count()) }}</span>
        <span class="badge badge-sm">{{ trans_choice('kopling-sports-management::messages.moderation.match_count', $team->matches_count ?? $team->matches()->count()) }}</span>
        <span class="badge badge-sm badge-ghost">{{ __('kopling-sports-management::messages.moderation.created', ['date' => $team->created_at->translatedFormat('j M Y')]) }}</span>
    </div>
</div>
<ul class="text-sm flex flex-col gap-1">
    @foreach ($team->staff as $staffPerson)
        <li class="flex flex-wrap items-center gap-2">
            <span>{{ $staffPerson->name }}</span>
            <span class="opacity-60">{{ $staffPerson->email }}</span>
            @if ($staffPerson->pivot->owner)
                <span class="badge badge-xs badge-outline">{{ __('kopling-sports-management::messages.owner') }}</span>
            @endif
            @if ($sanctionable ?? false)
                @includeIf('kopling-moderation::queue.sanction-form', ['person' => $staffPerson])
            @endif
        </li>
    @endforeach
</ul>
