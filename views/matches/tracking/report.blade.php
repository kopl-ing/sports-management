@use('Kopling\SportsManagement\MatchGoal')
@use('Kopling\SportsManagement\MatchState')
@use('Kopling\SportsManagement\PeriodType')
@use('Kopling\SportsManagement\Position')
@use('Kopling\SportsManagement\SubstitutionDirection')
<div class="flex flex-col gap-2">
    <a href="{{ route('kopling-sports-management::sports-management/matches.show', [$team, $match]) }}" class="link link-hover text-sm opacity-60">
        {{ __('kopling-sports-management::messages.back_to_match') }}
    </a>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $team->name }} &ndash; {{ $match->opponent_name }}</h1>
            <p class="text-sm opacity-60">{{ $match->scheduled_at->translatedFormat('l j F Y, H:i') }} &middot; {{ $match->home_away->label() }}</p>
        </div>
        <span class="badge {{ $state === MatchState::Live ? 'badge-error' : 'badge-ghost' }}">{{ $state->label() }}</span>
    </div>
</div>


<div class="grid md:grid-cols-2 gap-8">
    <section class="flex flex-col gap-3">
        <h2 class="text-lg font-semibold">{{ __('kopling-sports-management::messages.events') }}</h2>
        @forelse ($timeline->periods()->reverse() as $period)
            @php
                $events = $match->goals->where('period_id', $period->id)
                    ->concat($match->substitutions->where('period_id', $period->id))
                    ->sortBy([['offset_seconds', 'desc'], ['created_at', 'desc']]);
                $modalId = 'modal-period-'.$period->id;
            @endphp
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2 border-b border-base-300 pb-1">
                    <span class="font-semibold">{{ $timeline->label($period) }}</span>
                    <span class="text-sm opacity-60 font-mono tabular-nums">
                        {{ $period->isRunning() ? __('kopling-sports-management::messages.running') : $clock($timeline->length($period)) }}
                    </span>
                    @if ($canTrack)
                        <div class="flex gap-1 ms-auto">
                            <x-k::modal :label="__('kopling-sports-management::messages.edit')" :id="$modalId">
                                <x-slot:trigger>{{ __('kopling-sports-management::messages.edit') }}</x-slot:trigger>
                                <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.periods.update', [$team, $match, $period]) }}" class="flex flex-col gap-4">
                                    @csrf
                                    <h2 class="text-lg font-semibold">{{ $timeline->label($period) }}</h2>
                                    <x-k::form.select :data="['name' => 'type', 'label' => __('kopling-sports-management::messages.type'), 'options' => collect(PeriodType::cases())->mapWithKeys(fn ($case) => [$case->value => __('kopling-sports-management::messages.period_type.'.$case->value)])->all(), 'value' => $period->type->value]" />
                                    <x-k::form.input :data="['name' => 'duration_minutes', 'label' => __('kopling-sports-management::messages.duration_minutes'), 'type' => 'number', 'value' => $period->duration_seconds !== null ? intdiv($period->duration_seconds, 60) : '']" />
                                    <button type="submit" class="btn btn-primary self-start">{{ __('kopling-sports-management::messages.save') }}</button>
                                </form>
                            </x-k::modal>
                            <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.periods.destroy', [$team, $match, $period]) }}"
                                  hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.confirm_delete_period') }}">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('kopling-sports-management::messages.delete') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
                <ul class="flex flex-col">
                    @foreach ($events as $event)
                        @php
                            $isGoal = $event instanceof MatchGoal;
                            $destroyRoute = $isGoal
                                ? route('kopling-sports-management::sports-management/matches.goals.destroy', [$team, $match, $event])
                                : route('kopling-sports-management::sports-management/matches.substitutions.destroy', [$team, $match, $event]);
                        @endphp
                        <li class="flex items-center gap-2 py-1">
                            <span class="w-10 text-sm opacity-60 tabular-nums">{{ $minute($timeline->matchSecond($period, $event->offset_seconds)) }}</span>
                            @if ($isGoal)
                                @if ($event->opponent)
                                    <span class="badge badge-sm badge-error">{{ __('kopling-sports-management::messages.goal') }}</span>
                                    {{ $match->opponent_name }}
                                @else
                                    <span class="badge badge-sm badge-success">{{ __('kopling-sports-management::messages.goal') }}</span>
                                    {{ $name($event->scorer_team_member_id) }}
                                    @if ($event->assist_team_member_id)
                                        <span class="text-sm opacity-60">({{ __('kopling-sports-management::messages.assist') }}: {{ $name($event->assist_team_member_id) }})</span>
                                    @endif
                                @endif
                            @else
                                <span class="opacity-80">
                                    {{ __($event->direction === SubstitutionDirection::On ? 'kopling-sports-management::messages.came_on' : 'kopling-sports-management::messages.went_off', ['name' => $name($event->team_member_id)]) }}
                                </span>
                            @endif
                            @if ($canTrack)
                                <form autocomplete="off" method="POST" action="{{ $destroyRoute }}" hx-boost="true" class="ms-auto"
                                      hx-confirm="{{ __('kopling-sports-management::messages.confirm_delete_event') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-ghost text-error">&times;</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="opacity-60">{{ __('kopling-sports-management::messages.no_periods') }}</p>
        @endforelse

    </section>

    <section class="flex flex-col gap-3">
        <h2 class="text-lg font-semibold">{{ __('kopling-sports-management::messages.time_played') }}</h2>
        <ul class="flex flex-col gap-1">
            @foreach ($members->sortByDesc(fn ($member) => $played[$member->id] ?? 0) as $member)
                <li class="flex items-center gap-2 bg-base-100 border border-base-300 rounded-box px-3 py-1.5">
                    <span class="status {{ array_key_exists($member->id, $onField) ? 'status-success' : 'status-neutral' }}"
                          aria-label="{{ array_key_exists($member->id, $onField) ? __('kopling-sports-management::messages.on_field') : __('kopling-sports-management::messages.bench') }}"></span>
                    {{ $member->person->name }}
                    <span class="ms-auto font-mono tabular-nums text-sm">{{ $clock($played[$member->id] ?? 0) }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</div>

@if ($canTrack)
    <details class="collapse collapse-arrow card-border bg-base-100">
        <summary class="collapse-title font-semibold">{{ __('kopling-sports-management::messages.enter_afterwards') }}</summary>
        <div class="collapse-content flex flex-col gap-6">
            <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.periods.store', [$team, $match]) }}" hx-boost="true" class="flex flex-wrap items-center gap-2">
                @csrf
                <select name="type" class="select select-sm" aria-label="{{ __('kopling-sports-management::messages.type') }}">
                    @foreach (PeriodType::cases() as $case)
                        <option value="{{ $case->value }}">{{ __('kopling-sports-management::messages.period_type.'.$case->value) }}</option>
                    @endforeach
                </select>
                <input type="number" name="duration_minutes" min="0" max="240" required class="input input-sm w-24"
                       aria-label="{{ __('kopling-sports-management::messages.duration_minutes') }}"
                       placeholder="{{ __('kopling-sports-management::messages.duration_minutes') }}">
                <button type="submit" class="btn btn-sm">{{ __('kopling-sports-management::messages.add_period') }}</button>
            </form>
            @if ($timeline->periods()->isNotEmpty())
                <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.goals.store', [$team, $match]) }}" hx-boost="true" class="flex flex-col gap-3">
                    @csrf
                    <div class="flex flex-col gap-3">
                        <h3 class="font-semibold">{{ __('kopling-sports-management::messages.goal') }}</h3>
                        @include('kopling-sports-management::matches.tracking.when')
                        <select name="scorer_team_member_id" class="select select-sm" aria-label="{{ __('kopling-sports-management::messages.scorer') }}">
                            <option value="">{{ __('kopling-sports-management::messages.scorer') }}: {{ __('kopling-sports-management::messages.scorer_unknown') }}</option>
                            @foreach ($members->sortByDesc(fn ($member) => array_key_exists($member->id, $onField)) as $member)
                                <option value="{{ $member->id }}">{{ $member->person->name }}</option>
                            @endforeach
                        </select>
                        <select name="assist_team_member_id" class="select select-sm" aria-label="{{ __('kopling-sports-management::messages.assist') }}">
                            <option value="">{{ __('kopling-sports-management::messages.no_assist') }}</option>
                            @foreach ($members->sortByDesc(fn ($member) => array_key_exists($member->id, $onField)) as $member)
                                <option value="{{ $member->id }}">{{ $member->person->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-success btn-sm self-start">{{ __('kopling-sports-management::messages.goal_for_us') }}</button>
                    </div>
                </form>

                <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.goals.store', [$team, $match]) }}" hx-boost="true" class="flex flex-col gap-3">
                    @csrf
                    <input type="hidden" name="opponent" value="1">
                    <div class="flex flex-row flex-wrap items-center gap-3">
                        @include('kopling-sports-management::matches.tracking.when')
                        <button type="submit" class="btn btn-error btn-sm btn-outline">{{ __('kopling-sports-management::messages.goal_opponent', ['opponent' => $match->opponent_name]) }}</button>
                    </div>
                </form>
                <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.substitutions.store', [$team, $match]) }}" hx-boost="true" class="flex flex-col gap-3">
                    @csrf
                    <div class="flex flex-col gap-3">
                        <h3 class="font-semibold">{{ __('kopling-sports-management::messages.substitution') }}</h3>
                        @include('kopling-sports-management::matches.tracking.when')
                        <div class="flex gap-2">
                            <select name="off_team_member_id" class="select select-sm" aria-label="{{ __('kopling-sports-management::messages.player_off') }}">
                                <option value="">{{ __('kopling-sports-management::messages.player_off') }}: {{ __('kopling-sports-management::messages.nobody') }}</option>
                                @foreach (array_keys($onField) as $memberId)
                                    <option value="{{ $memberId }}">{{ $name($memberId) }}</option>
                                @endforeach
                            </select>
                            <select name="on_team_member_id" class="select select-sm" aria-label="{{ __('kopling-sports-management::messages.player_on') }}">
                                <option value="">{{ __('kopling-sports-management::messages.player_on') }}: {{ __('kopling-sports-management::messages.nobody') }}</option>
                                @foreach ($bench as $memberId)
                                    <option value="{{ $memberId }}">{{ $name($memberId) }}</option>
                                @endforeach
                            </select>
                            <select name="zone" class="select select-sm" aria-label="{{ __('kopling-sports-management::messages.zone') }}">
                                <option value="">{{ __('kopling-sports-management::messages.zone') }}</option>
                                @foreach (Position::cases() as $zone)
                                    <option value="{{ $zone->value }}">{{ $zone->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm self-start">{{ __('kopling-sports-management::messages.substitute') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </details>
@endif

@can('kopling-sports-management::manage-matches')
    <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.destroy', [$team, $match]) }}"
          hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.confirm_delete_match') }}" class="self-start">
        @csrf
        <button type="submit" class="btn btn-error btn-outline">{{ __('kopling-sports-management::messages.delete_match') }}</button>
    </form>
@endcan
