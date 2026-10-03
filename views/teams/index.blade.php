@extends('kopling-sports-management::layouts.sports-management')

@section('content')
    <div class="max-w-3xl flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ __('kopling-sports-management::messages.teams') }}</h1>

            @can('kopling-sports-management::manage-teams')
            <x-k::modal :label="__('kopling-sports-management::messages.create_team')" id="modal-team-create">
                <x-slot:trigger>{{ __('kopling-sports-management::messages.create_team') }}</x-slot:trigger>
                <form method="POST" action="{{ route('kopling-sports-management::sports-management/teams.store') }}" class="flex flex-col gap-4">
                    @csrf
                    <h2 class="text-lg font-semibold">{{ __('kopling-sports-management::messages.create_team') }}</h2>
                    <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-sports-management::messages.name'), 'value' => old('name'), 'required' => true]" />
                    <x-k::form.input :data="['name' => 'club', 'label' => __('kopling-sports-management::messages.club'), 'value' => old('club')]" />
                    <x-k::form.input :data="['name' => 'season', 'label' => __('kopling-sports-management::messages.season'), 'value' => old('season', \Kopling\SportsManagement\Team::currentSeason()), 'placeholder' => \Kopling\SportsManagement\Team::currentSeason(), 'required' => true]" />
                    @include('kopling-sports-management::teams.sport-fields', [
                        'sport' => \Kopling\SportsManagement\Sport::tryFrom((string) old('sport')) ?? \Kopling\SportsManagement\Sport::Football,
                        'presetId' => (string) old('format_preset_id'),
                        'sportEditable' => true,
                    ])
                    @if ($errors->any())
                        <p class="text-error text-sm">{{ $errors->first() }}</p>
                    @endif
                    <button type="submit" class="btn btn-primary self-start">{{ __('kopling-sports-management::messages.save') }}</button>
                </form>
            </x-k::modal>
            @endcan
        </div>

        @if ($invitations->isNotEmpty())
            <section class="card card-border bg-base-100">
                <div class="card-body gap-3">
                    <h2 class="card-title">{{ __('kopling-sports-management::messages.invitations') }}</h2>
                    <ul class="list">
                        @foreach ($invitations as $invitation)
                            <li class="list-row items-center">
                                <div class="list-col-grow min-w-0">
                                    <p class="font-semibold truncate">{{ $invitation->team->name }}</p>
                                    <p class="text-sm opacity-60 truncate">{{ implode(' · ', array_filter([
                                        $invitation->team->club,
                                        $invitation->inviter ? __('kopling-sports-management::messages.invited_by', ['name' => $invitation->inviter->name]) : null,
                                    ])) }}</p>
                                </div>
                                <form method="POST" action="{{ route('kopling-sports-management::sports-management/invitations.decline', $invitation) }}" hx-boost="true">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-ghost">{{ __('kopling-sports-management::messages.decline') }}</button>
                                </form>
                                <form method="POST" action="{{ route('kopling-sports-management::sports-management/invitations.accept', $invitation) }}" hx-boost="true">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('kopling-sports-management::messages.accept') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        @if ($teams->isEmpty())
            <p class="opacity-60">{{ __('kopling-sports-management::messages.no_teams') }}</p>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($teams as $team)
                    <a href="{{ route('kopling-sports-management::sports-management/teams.show', $team) }}" class="card card-border bg-base-100 hover:border-primary">
                        <div class="card-body flex-row items-center justify-between py-4">
                            <div>
                                <p class="font-semibold">{{ $team->name }}</p>
                                <p class="text-sm opacity-60">{{ $team->subtitle() }}</p>
                            </div>
                            @if ($team->formatPreset)
                                <span class="badge badge-outline">{{ $team->formatPreset->name }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
