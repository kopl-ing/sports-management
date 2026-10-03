@use('Kopling\Core\Ux\Context')
@php
    $status = $availability->get($member->id);
    $statusDot = ['available' => 'status-success', 'maybe' => 'status-warning', 'absent' => 'status-error'];
@endphp
<div data-sm-player="{{ $member->id }}" draggable="false" @if (in_array($member->id, $unavailable ?? [], true)) data-sm-unavailable @endif
     class="flex flex-col items-center gap-1 select-none {{ $editable ? 'touch-none cursor-grab' : '' }} rounded-full data-[sm-selected]:ring-4 data-[sm-selected]:ring-primary data-[sm-over]:ring-4 data-[sm-over]:ring-accent data-[sm-dragging]:opacity-40 data-[sm-pending]:animate-pulse data-[sm-refused]:ring-4 data-[sm-refused]:ring-error group-data-[sm-goal]:ring-2 group-data-[sm-goal]:ring-success group-data-[sm-sanction]:ring-2 group-data-[sm-sanction]:ring-warning data-[sm-unavailable]:opacity-50">
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
                    'prefix' => array_key_exists($member->id, $onField) ? '' : __('kopling-sports-management::messages.played'),
                    'class' => 'indicator-item indicator-center badge badge-sm tabular-nums',
                    'style' => $playedTint($member->id),
                    'limit' => $fairShare,
                    'limitStyle' => $fairShareStyle,
                ])
                @php($benched = $matchSeconds - ($played[$member->id] ?? 0))
                @php($isOnField = array_key_exists($member->id, $onField))
                @if (array_key_exists($member->id, $penaltyLeft ?? []))
                    @include('kopling-sports-management::matches.tracking.clock', [
                        'seconds' => $penaltyLeft[$member->id],
                        'countdown' => true,
                        'ticking' => $ticking,
                        'class' => 'indicator-item indicator-bottom indicator-center badge badge-sm badge-error tabular-nums',
                    ])
                @elseif (in_array($member->id, $unavailable ?? [], true))
                    <span class="indicator-item indicator-bottom indicator-center badge badge-sm badge-error">{{ __('kopling-sports-management::messages.out') }}</span>
                @elseif ($fairBench && (! $isOnField || $benched >= 60))
                    @include('kopling-sports-management::matches.tracking.clock', [
                        'seconds' => $benched,
                        'minutesOnly' => true,
                        'ticking' => $ticking && ! $isOnField,
                        'prefix' => $isOnField ? '' : __('kopling-sports-management::messages.benched'),
                        'class' => 'indicator-item indicator-bottom indicator-center badge badge-sm tabular-nums',
                        'style' => $benched >= $fairBench ? $fairShareStyle : '',
                        'limit' => $fairBench,
                        'limitStyle' => $fairShareStyle,
                    ])
                @endif
            @endif
            @if ($member->guest)
                <span class="indicator-item indicator-start badge badge-xs badge-outline">{{ __('kopling-sports-management::messages.guest_short') }}</span>
            @endif
        </x-slot:indicators>
    </x-k::person.avatar>
</div>
