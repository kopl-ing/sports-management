@extends('kopling-sports-management::layouts.sports-management')
@php
    $reopening = old('_form');
    $canManage = Gate::allows('kopling-sports-management::manage-matches');
    $preset = $match->effectiveFormatPreset();
    $availableCount = $availability->filter(fn ($status) => $status === \Kopling\SportsManagement\AvailabilityStatus::Available)->count();
    $statusBadge = [
        'available' => 'badge-success',
        'maybe' => 'badge-warning',
        'absent' => 'badge-error',
    ];
    $statusChecked = [
        'available' => 'has-checked:btn-success',
        'maybe' => 'has-checked:btn-warning',
        'absent' => 'has-checked:btn-error',
    ];
@endphp

@section('content')
    <div class="max-w-4xl flex flex-col gap-8">
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-2">
                <a href="{{ route('kopling-sports-management::sports-management/teams.show', $team) }}" class="link link-hover text-sm opacity-60 min-w-0 truncate">
                    {{ __('kopling-sports-management::messages.back_to_team', ['team' => $team->name]) }}
                </a>
                @if ($canManage)
                    <x-k::dropdown :label="__('kopling-sports-management::messages.more_actions')">
                        <x-slot:trigger>&vellip;</x-slot:trigger>
                        <li>
                            <button type="button" data-modal-show="modal-match-edit">{{ __('kopling-sports-management::messages.edit_match') }}</button>
                        </li>
                        <li>
                            <form method="POST" action="{{ route('kopling-sports-management::sports-management/matches.destroy', [$team, $match]) }}"
                                  hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.confirm_delete_match') }}">
                                @csrf
                                <button type="submit" class="w-full text-start text-error">{{ __('kopling-sports-management::messages.delete_match') }}</button>
                            </form>
                        </li>
                    </x-k::dropdown>
                    {{-- Outside the dropdown: a dialog inside a closed popover can't be shown. --}}
                    <x-k::modal :label="__('kopling-sports-management::messages.edit_match')" id="modal-match-edit">
                        <x-slot:trigger class="hidden"></x-slot:trigger>
                        @include('kopling-sports-management::matches.form', [
                            'action' => route('kopling-sports-management::sports-management/matches.update', [$team, $match]),
                            'formId' => 'modal-match-edit',
                            'title' => __('kopling-sports-management::messages.edit_match'),
                        ])
                    </x-k::modal>
                @endif
            </div>
            <div>
                <h1 class="text-2xl font-bold">
                    {{ $match->opponent_name }}
                    <span class="badge badge-outline align-middle">
                        <x-k::icon :name="'kopling-sports-management::'.$match->home_away->value" />
                        {{ $match->home_away->label() }}
                    </span>
                    @if ($timeline->state() !== \Kopling\SportsManagement\MatchState::Planned)
                        <span class="tabular-nums ms-2">{{ $timeline->score()['us'] }} &ndash; {{ $timeline->score()['them'] }}</span>
                    @endif
                </h1>
                <p class="text-sm opacity-60">
                    {{ $match->scheduled_at->translatedFormat('l j F Y, H:i') }}
                    @if ($preset)
                        &middot; {{ $preset->name }}
                        @if ($preset->rules_url)
                            (<a href="{{ $preset->rules_url }}" class="link" target="_blank" rel="noopener noreferrer">{{ __('kopling-sports-management::messages.rules') }}</a>)
                        @endif
                    @endif
                </p>
                @if ($match->location_address)
                    <p class="text-sm whitespace-pre-line mt-1">{{ $match->location_address }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($timeline->state() === \Kopling\SportsManagement\MatchState::Ended)
                    <a href="{{ route('kopling-sports-management::sports-management/matches.report', [$team, $match]) }}" class="btn btn-primary">
                        {{ __('kopling-sports-management::messages.view_tracking') }}
                    </a>
                    @can('kopling-sports-management::track-matches')
                        <a href="{{ route('kopling-sports-management::sports-management/matches.track', [$team, $match]) }}" class="btn">
                            {{ __('kopling-sports-management::messages.track_match') }}
                        </a>
                    @endcan
                @else
                    <a href="{{ route('kopling-sports-management::sports-management/matches.track', [$team, $match]) }}" class="btn btn-primary">
                        {{ Gate::allows('kopling-sports-management::track-matches') ? __('kopling-sports-management::messages.track_match') : __('kopling-sports-management::messages.view_tracking') }}
                    </a>
                @endif
            </div>
        </div>

        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ __('kopling-sports-management::messages.availability') }}</h2>
                <span class="badge {{ $preset ? ($availableCount >= $preset->players_on_field ? 'badge-success' : 'badge-error') : 'badge-ghost' }}">
                    {{ $preset
                        ? __('kopling-sports-management::messages.available_count', ['available' => $availableCount, 'needed' => $preset->players_on_field])
                        : __('kopling-sports-management::messages.available_count_no_preset', ['available' => $availableCount]) }}
                </span>
            </div>

            @if ($members->isEmpty())
                <p class="opacity-60">{{ __('kopling-sports-management::messages.no_members') }}</p>
            @else
                <form method="POST" action="{{ route('kopling-sports-management::sports-management/matches.availability', [$team, $match]) }}" hx-boost="true" class="flex flex-col gap-3">
                    @csrf
                    <ul class="flex flex-col gap-2">
                        @foreach ($members as $member)
                            @php $status = $availability->get($member->id); @endphp
                            <li class="flex items-center justify-between gap-2 bg-base-100 border border-base-300 rounded-box px-4 py-2">
                                <span class="min-w-0 truncate">
                                    @if ($member->jersey_number)
                                        <span class="opacity-60 tabular-nums">#{{ $member->jersey_number }}</span>
                                    @endif
                                    {{ $member->person->name }}
                                    @if ($member->guest)
                                        <span class="badge badge-sm badge-outline">{{ __('kopling-sports-management::messages.guest') }}</span>
                                    @endif
                                </span>
                                @if ($canManage)
                                    <div class="join shrink-0">
                                        @foreach (\Kopling\SportsManagement\AvailabilityStatus::cases() as $case)
                                            <label class="join-item btn btn-sm btn-square has-focus-visible:outline-2 {{ $statusChecked[$case->value] }}" title="{{ $case->label() }}">
                                                <input type="radio" class="sr-only" name="availability[{{ $member->id }}]" value="{{ $case->value }}"
                                                       aria-label="{{ $case->label() }}" @checked($status === $case)>
                                                <x-k::icon :name="'kopling-sports-management::'.$case->value" />
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="badge {{ $status ? $statusBadge[$status->value] : 'badge-ghost' }}">
                                        {{ $status?->label() ?? __('kopling-sports-management::messages.availability_unknown') }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($canManage)
                        <button type="submit" class="btn btn-primary self-start">{{ __('kopling-sports-management::messages.save_availability') }}</button>
                    @endif
                </form>
            @endif
        </section>

    </div>
@endsection
