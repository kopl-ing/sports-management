@extends('kopling-sports-management::layouts.sports-management')
@php
    // Same _form convention as kopling-tags::admin.index.
    $reopening = old('_form');
    $canManageTeam = Gate::allows('kopling-sports-management::manage-teams');
    $canManageMatches = Gate::allows('kopling-sports-management::manage-matches');
    $positions = \Kopling\SportsManagement\Position::options($team->sport);
@endphp

@section('content')
    <div class="max-w-4xl flex flex-col gap-8">
        <div class="flex flex-col gap-3">
            <a href="{{ route('kopling-sports-management::sports-management/teams.index') }}" class="link link-hover text-sm opacity-60 self-start">
                {{ __('kopling-sports-management::messages.back_to_teams') }}
            </a>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ $team->name }}</h1>
                    <p class="text-sm opacity-60">{{ $team->subtitle() }}
                        @if ($team->formatPreset)
                            &middot; {{ $team->formatPreset->name }}
                        @endif
                    </p>
                </div>

                @if ($canManageTeam)
                <x-k::modal :label="__('kopling-sports-management::messages.edit_team')" id="modal-team-edit">
                    <x-slot:trigger>{{ __('kopling-sports-management::messages.edit_team') }}</x-slot:trigger>
                    <form method="POST" action="{{ route('kopling-sports-management::sports-management/teams.update', $team) }}" class="flex flex-col gap-4">
                        @csrf
                        <input type="hidden" name="_form" value="modal-team-edit">
                        <h2 class="text-lg font-semibold">{{ __('kopling-sports-management::messages.edit_team') }}</h2>
                        <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-sports-management::messages.name'), 'value' => $reopening === 'modal-team-edit' ? old('name') : $team->name, 'required' => true]" />
                        <x-k::form.input :data="['name' => 'club', 'label' => __('kopling-sports-management::messages.club'), 'value' => $reopening === 'modal-team-edit' ? old('club') : $team->club]" />
                        <x-k::form.input :data="['name' => 'season', 'label' => __('kopling-sports-management::messages.season'), 'value' => $reopening === 'modal-team-edit' ? old('season') : $team->season, 'required' => true]" />
                        @include('kopling-sports-management::teams.sport-fields', [
                            'sport' => $reopening === 'modal-team-edit' && ! $sportLocked ? (\Kopling\SportsManagement\Sport::tryFrom((string) old('sport')) ?? $team->sport) : $team->sport,
                            'presetId' => (string) ($reopening === 'modal-team-edit' ? old('format_preset_id') : $team->format_preset_id),
                            'sportEditable' => ! $sportLocked,
                        ])
                        @if ($reopening === 'modal-team-edit' && $errors->any())
                            <p class="text-error text-sm">{{ $errors->first() }}</p>
                        @endif
                        <button type="submit" class="btn btn-primary self-start">{{ __('kopling-sports-management::messages.save') }}</button>
                    </form>
                </x-k::modal>
                @endif
            </div>
        </div>

        @if ($team->members->isNotEmpty() || $upcomingMatches->isNotEmpty() || $pastMatches->isNotEmpty())
            @include('kopling-sports-management::teams.matches')
        @endif

        <section class="card card-border bg-base-100">
            <div class="card-body gap-3">
                <div class="flex items-center justify-between">
                    <h2 class="card-title">
                        {{ __('kopling-sports-management::messages.roster') }}
                        @php $guestCount = $team->members->where('guest', true)->count(); @endphp
                        <span class="badge badge-sm">{{ trans_choice('kopling-sports-management::messages.player_count', $team->members->count() - $guestCount) }}</span>
                        @if ($guestCount > 0)
                            <span class="badge badge-sm badge-outline">{{ trans_choice('kopling-sports-management::messages.guest_count', $guestCount) }}</span>
                        @endif
                    </h2>

                    @if ($canManageTeam)
                    <x-k::modal :label="__('kopling-sports-management::messages.add_member')" id="modal-member-create">
                        <x-slot:trigger>{{ __('kopling-sports-management::messages.add_member') }}</x-slot:trigger>
                        <form method="POST" action="{{ route('kopling-sports-management::sports-management/teams.members.store', $team) }}" class="flex flex-col gap-4">
                            @csrf
                            <input type="hidden" name="_form" value="modal-member-create">
                            <h2 class="text-lg font-semibold">{{ __('kopling-sports-management::messages.add_member') }}</h2>
                            <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-sports-management::messages.name'), 'value' => $reopening === 'modal-member-create' ? old('name') : '']" />
                            <x-k::form.input :data="['name' => 'jersey_number', 'label' => __('kopling-sports-management::messages.jersey_number'), 'value' => $reopening === 'modal-member-create' ? old('jersey_number') : '']" />
                            <x-k::form.multi-select :data="['name' => 'positions', 'label' => __('kopling-sports-management::messages.positions_label'), 'options' => $positions, 'value' => $reopening === 'modal-member-create' ? old('positions', []) : []]" />
                            <x-k::form.toggle :data="['name' => 'guest', 'label' => __('kopling-sports-management::messages.guest'), 'value' => $reopening === 'modal-member-create' ? old('guest') : false]" />
                            @if ($reopening === 'modal-member-create' && $errors->any())
                                <p class="text-error text-sm">{{ $errors->first() }}</p>
                            @endif
                            <button type="submit" class="btn btn-primary self-start">{{ __('kopling-sports-management::messages.save') }}</button>
                        </form>
                    </x-k::modal>
                    @endif
                </div>

                @if ($team->members->isEmpty())
                    <p class="opacity-60">{{ __('kopling-sports-management::messages.no_members') }}</p>
                @else
                    <ul class="list">
                        @foreach ($team->members as $member)
                            @php $modalId = 'modal-member-edit-'.$member->id; @endphp
                            <li class="list-row items-center">
                                <div class="list-col-grow min-w-0">
                                    <div class="truncate">
                                        {{ $member->person->name }}
                                        @if ($member->jersey_number !== null && $member->jersey_number !== '')
                                            <span class="text-sm opacity-60">#{{ $member->jersey_number }}</span>
                                        @endif
                                    </div>
                                    @if ($member->positions?->isNotEmpty() || $member->guest)
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach ($member->positions ?? [] as $position)
                                                <span class="badge badge-sm" title="{{ $position->label($team->sport) }}">{{ $position->value }}</span>
                                            @endforeach
                                            @if ($member->guest)
                                                <span class="badge badge-sm badge-outline">{{ __('kopling-sports-management::messages.guest') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                @if ($canManageTeam)
                                    <x-k::modal :label="__('kopling-sports-management::messages.edit')" :id="$modalId">
                                        <x-slot:trigger>{{ __('kopling-sports-management::messages.edit') }}</x-slot:trigger>
                                        <form method="POST" action="{{ route('kopling-sports-management::sports-management/teams.members.update', [$team, $member]) }}" class="flex flex-col gap-4">
                                            @csrf
                                            <input type="hidden" name="_form" value="{{ $modalId }}">
                                            <h2 class="text-lg font-semibold">{{ $member->person->name }}</h2>
                                            <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-sports-management::messages.name'), 'value' => $reopening === $modalId ? old('name') : $member->person->name]" />
                                            <x-k::form.input :data="['name' => 'jersey_number', 'label' => __('kopling-sports-management::messages.jersey_number'), 'value' => $reopening === $modalId ? old('jersey_number') : $member->jersey_number]" />
                                            <x-k::form.multi-select :data="['name' => 'positions', 'label' => __('kopling-sports-management::messages.positions_label'), 'options' => $positions, 'value' => $reopening === $modalId ? old('positions', []) : ($member->positions?->pluck('value') ?? [])]" />
                                            <x-k::form.toggle :data="['name' => 'guest', 'label' => __('kopling-sports-management::messages.guest'), 'value' => $reopening === $modalId ? old('guest') : $member->guest]" />
                                            @if ($reopening === $modalId && $errors->any())
                                                <p class="text-error text-sm">{{ $errors->first() }}</p>
                                            @endif
                                            <button type="submit" class="btn btn-primary self-start">{{ __('kopling-sports-management::messages.save') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('kopling-sports-management::sports-management/teams.members.destroy', [$team, $member]) }}"
                                              hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.confirm_delete_member') }}"
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

        <section class="card card-border bg-base-100">
            <div class="card-body gap-3">
                <h2 class="card-title">{{ __('kopling-sports-management::messages.staff') }}</h2>
                <ul class="list">
                    @foreach ($team->staff as $staffPerson)
                        @php $isMe = $staffPerson->is(auth()->user()); @endphp
                        <li class="list-row items-center">
                            <div class="list-col-grow min-w-0 truncate">
                                {{ $staffPerson->name }}
                                @if ($staffPerson->pivot->owner)
                                    <span class="badge badge-sm badge-outline">{{ __('kopling-sports-management::messages.owner') }}</span>
                                @endif
                                <span class="opacity-60 text-sm">{{ $staffPerson->email }}</span>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                @if ($isOwner && $canManageTeam && ! $staffPerson->pivot->owner)
                                    <form method="POST" action="{{ route('kopling-sports-management::sports-management/teams.staff.owner', [$team, $staffPerson]) }}"
                                          hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.confirm_make_owner') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('kopling-sports-management::messages.make_owner') }}</button>
                                    </form>
                                @endif
                                @if ($isMe || ($isOwner && $canManageTeam && ! $staffPerson->pivot->owner))
                                    <form method="POST" action="{{ route('kopling-sports-management::sports-management/teams.staff.destroy', [$team, $staffPerson]) }}"
                                          hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.'.($isMe ? 'confirm_leave_team' : 'confirm_remove_staff')) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-sports-management::messages.'.($isMe ? 'leave_team' : 'remove')) }}</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($invitations->isNotEmpty())
                    <h3 class="text-sm font-semibold opacity-60">{{ __('kopling-sports-management::messages.pending_invitations') }}</h3>
                    <ul class="list">
                        @foreach ($invitations as $invitation)
                            <li class="list-row items-center">
                                <div class="list-col-grow min-w-0 truncate opacity-60">{{ $invitation->email }}</div>
                                @if ($canManageTeam)
                                    <form method="POST" action="{{ route('kopling-sports-management::sports-management/teams.invitations.destroy', [$team, $invitation]) }}" hx-boost="true">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('kopling-sports-management::messages.revoke') }}</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($canManageTeam)
                <form method="POST" action="{{ route('kopling-sports-management::sports-management/teams.invitations.store', $team) }}" hx-boost="true" class="flex gap-2">
                    @csrf
                    <input type="email" name="email" placeholder="{{ __('kopling-sports-management::messages.staff_email_placeholder') }}" class="input" required>
                    <button type="submit" class="btn btn-primary">{{ __('kopling-sports-management::messages.add_staff') }}</button>
                </form>
                @endif
                @if (session('status'))
                    <p class="text-success text-sm">{{ session('status') }}</p>
                @endif
                @error('email')
                    <p class="text-error text-sm">{{ $message }}</p>
                @enderror
                @error('staff')
                    <p class="text-error text-sm">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-2">
            @if ($canManageTeam && $isOwner)
                <form method="POST" action="{{ route('kopling-sports-management::sports-management/teams.destroy', $team) }}"
                      hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.confirm_delete_team') }}">
                    @csrf
                    <button type="submit" class="btn btn-error btn-outline">{{ __('kopling-sports-management::messages.delete_team') }}</button>
                </form>
            @endif
            <x-k::portal.slot :name="\Kopling\SportsManagement\Extension::TEAM_CONTROL_SLOT" :context="new \Kopling\Core\Ux\Context(subject: $team)" />
        </div>
    </div>
@endsection
