@use('Kopling\Core\Ux\Context')
@php
    $status = $availability->get($member->id);
    $statusDot = ['available' => 'status-success', 'maybe' => 'status-warning', 'absent' => 'status-error'];
@endphp
<div data-sm-player="{{ $member->id }}" draggable="false"
     class="flex flex-col items-center gap-1 select-none {{ $editable ? 'touch-none cursor-grab' : '' }} rounded-full data-[sm-selected]:ring-4 data-[sm-selected]:ring-primary data-[sm-over]:ring-4 data-[sm-over]:ring-accent data-[sm-dragging]:opacity-40 data-[sm-pending]:animate-pulse data-[sm-refused]:ring-4 data-[sm-refused]:ring-error group-data-[sm-goal]:ring-2 group-data-[sm-goal]:ring-success">
    <x-k::person.avatar :context="new Context(subject: $member->person)" :initials="$initials[$member->id]" :mask="null" size="w-16">
        <x-slot:indicators>
            @if ($state === \Kopling\SportsManagement\MatchState::Planned)
                @if ($status)
                    <span class="indicator-item indicator-bottom status {{ $statusDot[$status->value] }}" aria-label="{{ $status->label() }}"></span>
                @endif
            @else
                @include('kopling-sports-management::matches.tracking.clock', [
                    'seconds' => $played[$member->id] ?? 0,
                    'minutesOnly' => true,
                    'ticking' => $ticking && array_key_exists($member->id, $onField),
                    'class' => 'indicator-item badge badge-sm tabular-nums',
                ])
            @endif
            @if ($member->guest)
                <span class="indicator-item indicator-start badge badge-xs badge-outline">{{ __('kopling-sports-management::messages.guest_short') }}</span>
            @endif
        </x-slot:indicators>
    </x-k::person.avatar>
</div>
