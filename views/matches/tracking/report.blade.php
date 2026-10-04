@use('Kopling\SportsManagement\MatchGoal')
@use('Kopling\SportsManagement\MatchSanction')
@use('Kopling\SportsManagement\MatchState')
@use('Kopling\SportsManagement\PeriodType')
@use('Kopling\SportsManagement\SubstitutionDirection')
@php
    $bench = $members->keys()->diff(array_keys($onField));
    $clock = fn (int $seconds) => intdiv($seconds, 60).':'.sprintf('%02d', $seconds % 60);
    $minute = fn (int $seconds) => intdiv($seconds, 60)."'";
    $name = fn (?string $memberId) => $memberId ? ($members->get($memberId)?->person->name ?? '?') : __('kopling-sports-management::messages.scorer_unknown');
    $score = $timeline->score();
    $timing = $canTrack && $duties['timing'];
    $scoring = $canTrack && $duties['scoring'];
    $sanctioning = $canTrack && $duties['sanctions'];
    $substituting = $canTrack && $isCoach;
    $showSanctions = $isCoach || $duties['sanctions'];
@endphp
<div class="flex flex-col gap-2">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">
                {{ $team->name }} &ndash; {{ $match->opponent_name }}
                @if ($state !== MatchState::Planned)
                    <span class="tabular-nums ms-2">{{ $score['us'] }} &ndash; {{ $score['them'] }}</span>
                @endif
            </h1>
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
                    ->concat($showSanctions ? $match->sanctions->where('period_id', $period->id) : [])
                    ->concat($isCoach ? $match->substitutions->where('period_id', $period->id)->whereNotIn('id', $match->sanctions->pluck('substitution_id')->filter()) : [])
                    ->sortBy([['offset_seconds', 'desc'], ['created_at', 'desc']]);
                $modalId = 'modal-period-'.$period->id;
            @endphp
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2 border-b border-base-300 pb-1">
                    <span class="font-semibold">{{ $timeline->label($period) }}</span>
                    <span class="text-sm opacity-60 font-mono tabular-nums">
                        {{ $period->isRunning() ? __('kopling-sports-management::messages.running') : $clock($timeline->length($period)) }}
                    </span>
                    @if ($timing)
                        <div class="flex gap-1 ms-auto">
                            <x-k::modal :label="__('kopling-sports-management::messages.edit')" :id="$modalId">
                                <x-slot:trigger>{{ __('kopling-sports-management::messages.edit') }}</x-slot:trigger>
                                <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.periods.update', [$team, $match, $period]) }}" class="flex flex-col gap-4">
                                    @csrf
                                    <h2 class="text-lg font-semibold">{{ $timeline->label($period) }}</h2>
                                    <x-k::form.select :data="['name' => 'type', 'label' => __('kopling-sports-management::messages.type'), 'options' => collect(PeriodType::cases())->mapWithKeys(fn ($case) => [$case->value => __('kopling-sports-management::messages.period_type.'.$case->value)])->all(), 'value' => $period->type->value]" />
                                    <x-k::form.input :data="['name' => 'duration_minutes', 'label' => __('kopling-sports-management::messages.duration_minutes'), 'type' => 'number', 'value' => $period->duration_seconds !== null ? intdiv($period->duration_seconds, 60) : '']" />
                                    <div class="flex gap-2">
                                        <button type="submit" class="btn btn-primary">{{ __('kopling-sports-management::messages.save') }}</button>
                                        <x-k::modal.cancel />
                                    </div>
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
                            $canDelete = match (true) {
                                $isGoal => $scoring,
                                $event instanceof MatchSanction => $sanctioning,
                                default => $substituting,
                            };
                            $destroyRoute = match (true) {
                                $isGoal => route('kopling-sports-management::sports-management/matches.goals.destroy', [$team, $match, $event]),
                                $event instanceof MatchSanction => route('kopling-sports-management::sports-management/matches.sanctions.destroy', [$team, $match, $event]),
                                default => route('kopling-sports-management::sports-management/matches.substitutions.destroy', [$team, $match, $event]),
                            };
                        @endphp
                        <li class="flex items-center gap-2 py-1">
                            <span class="w-10 text-sm opacity-60 tabular-nums">{{ $minute($timeline->matchSecond($period, $event->offset_seconds)) }}</span>
                            @if ($isGoal)
                                @if ($event->opponent)
                                    <span class="badge badge-sm badge-error">{{ count($pointValues) > 1 ? '+'.$event->points : $team->sport->trans('goal') }}</span>
                                    {{ $match->opponent_name }}
                                @else
                                    <span class="badge badge-sm badge-success">{{ count($pointValues) > 1 ? '+'.$event->points : $team->sport->trans('goal') }}</span>
                                    {{ $event->own_goal ? __('kopling-sports-management::messages.own_goal_by', ['opponent' => $match->opponent_name]) : $name($event->scorer_team_member_id) }}
                                    @if ($event->assist_team_member_id)
                                        <span class="text-sm opacity-60">({{ __('kopling-sports-management::messages.assist') }}: {{ $name($event->assist_team_member_id) }})</span>
                                    @endif
                                @endif
                            @elseif ($event instanceof MatchSanction)
                                <span class="badge badge-sm {{ $event->kind->badge() }}">{{ $event->kind->label() }}</span>
                                {{ $name($event->team_member_id) }}
                                @if ($event->kind->isTimePenalty() && $event->duration_seconds)
                                    <span class="text-sm opacity-60">({{ $minute($event->duration_seconds) }})</span>
                                @endif
                            @else
                                <span class="opacity-80">
                                    @if ($event->direction === SubstitutionDirection::On && $event->zone)
                                        {{ __('kopling-sports-management::messages.came_on_at', ['name' => $name($event->team_member_id), 'position' => $event->zone->label($team->sport)]) }}
                                    @else
                                        {{ __($event->direction === SubstitutionDirection::On ? 'kopling-sports-management::messages.came_on' : 'kopling-sports-management::messages.went_off', ['name' => $name($event->team_member_id)]) }}
                                    @endif
                                </span>
                            @endif
                            @if ($canDelete)
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
        <h2 class="text-lg font-semibold">{{ $team->sport->trans('scorers') }}</h2>
        <ul class="flex flex-col gap-1">
            @forelse ($timeline->contributions() as $memberId => $tally)
                <li class="flex items-center gap-2 bg-base-100 border border-base-300 rounded-box px-3 py-1.5">
                    {{ $name($memberId) }}
                    <span class="ms-auto flex gap-1">
                        @if ($tally['goals'])
                            <span class="badge badge-sm badge-success">{{ count($pointValues) > 1
                                ? trans_choice('kopling-sports-management::messages.points_count', $tally['points'])
                                : trans_choice('kopling-sports-management::messages.goals_count', $tally['goals']) }}</span>
                        @endif
                        @if ($tally['assists'])
                            <span class="badge badge-sm badge-ghost">{{ trans_choice('kopling-sports-management::messages.assists_count', $tally['assists']) }}</span>
                        @endif
                    </span>
                </li>
            @empty
                <li class="text-sm opacity-60">{{ $team->sport->trans('no_scorers') }}</li>
            @endforelse
        </ul>

        @if ($isCoach)
            <h2 class="text-lg font-semibold mt-5">{{ __('kopling-sports-management::messages.time_played') }}</h2>
            <ul class="flex flex-col gap-1">
                @foreach ($members->sortByDesc(fn ($member) => $played[$member->id] ?? 0) as $member)
                    <li class="flex items-center gap-2 bg-base-100 border border-base-300 rounded-box px-3 py-1.5">
                        <span class="status {{ array_key_exists($member->id, $onField) ? 'status-success' : 'status-neutral' }}"
                              aria-label="{{ array_key_exists($member->id, $onField) ? $team->sport->trans('on_field') : __('kopling-sports-management::messages.bench') }}"></span>
                        {{ $member->person->name }}
                        <span class="ms-auto font-mono tabular-nums text-sm">{{ $clock($played[$member->id] ?? 0) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

@if ($timing || $scoring || $sanctioning || $substituting)
    <details class="collapse collapse-arrow card-border bg-base-100">
        <summary class="collapse-title font-semibold">{{ __('kopling-sports-management::messages.enter_afterwards') }}</summary>
        <div class="collapse-content flex flex-col gap-6">
            @if ($timing)
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
            @endif
            @if ($timeline->periods()->isNotEmpty())
                @if ($scoring)
                    <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.goals.store', [$team, $match]) }}" hx-boost="true" class="flex flex-col gap-3">
                        @csrf
                        <div class="flex flex-col gap-3">
                            <h3 class="font-semibold">{{ $team->sport->trans('goal') }}</h3>
                            <div class="flex flex-wrap gap-2">
                                @include('kopling-sports-management::matches.tracking.when')
                                @include('kopling-sports-management::matches.tracking.points')
                            </div>
                            <select name="scorer_team_member_id" class="select select-sm" aria-label="{{ $team->sport->trans('scorer') }}">
                                <option value="">{{ $team->sport->trans('scorer') }}: {{ __('kopling-sports-management::messages.scorer_unknown') }}</option>
                                @foreach ($members->sortByDesc(fn ($member) => array_key_exists($member->id, $onField)) as $member)
                                    <option value="{{ $member->id }}">{{ $member->person->name }}</option>
                                @endforeach
                            </select>
                            @if ($pointValues === [1])
                                <label class="label text-sm">
                                    <input type="checkbox" name="own_goal" value="1" class="checkbox checkbox-sm">
                                    {{ __('kopling-sports-management::messages.own_goal_by', ['opponent' => $match->opponent_name]) }}
                                </label>
                            @endif
                            <select name="assist_team_member_id" class="select select-sm" aria-label="{{ __('kopling-sports-management::messages.assist') }}">
                                <option value="">{{ __('kopling-sports-management::messages.no_assist') }}</option>
                                @foreach ($members->sortByDesc(fn ($member) => array_key_exists($member->id, $onField)) as $member)
                                    <option value="{{ $member->id }}">{{ $member->person->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-success btn-sm self-start">{{ $team->sport->trans('goal_for_us') }}</button>
                        </div>
                    </form>

                    <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.goals.store', [$team, $match]) }}" hx-boost="true" class="flex flex-col gap-3">
                        @csrf
                        <input type="hidden" name="opponent" value="1">
                        <div class="flex flex-row flex-wrap items-center gap-3">
                            @include('kopling-sports-management::matches.tracking.when')
                            @include('kopling-sports-management::matches.tracking.points')
                            <button type="submit" class="btn btn-error btn-sm btn-outline">{{ $team->sport->trans('goal_opponent', ['opponent' => $match->opponent_name]) }}</button>
                        </div>
                    </form>
                @endif
                @if ($substituting)
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
                                    @foreach ($zones as $zone)
                                        <option value="{{ $zone->value }}">{{ $zone->label($team->sport) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm self-start">{{ __('kopling-sports-management::messages.substitute') }}</button>
                        </div>
                    </form>
                @endif
                @if ($sanctioning && $sanctionKinds !== [])
                    <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.sanctions.store', [$team, $match]) }}" hx-boost="true" class="flex flex-col gap-3">
                        @csrf
                        <h3 class="font-semibold">{{ __('kopling-sports-management::messages.sanction_button.'.$team->sport->value) }}</h3>
                        @include('kopling-sports-management::matches.tracking.when')
                        <div class="flex flex-wrap gap-2">
                            <select name="team_member_id" class="select select-sm" aria-label="{{ __('kopling-sports-management::messages.player') }}" required>
                                @foreach ($members as $member)
                                    <option value="{{ $member->id }}">{{ $member->person->name }}</option>
                                @endforeach
                            </select>
                            <select name="kind" class="select select-sm" aria-label="{{ __('kopling-sports-management::messages.sanction_kind') }}">
                                @foreach ($sanctionKinds as $kind)
                                    <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm self-start">{{ __('kopling-sports-management::messages.record') }}</button>
                    </form>
                @endif
            @endif
        </div>
    </details>
@endif

@if ($isCoach && Gate::allows('kopling-sports-management::manage-matches'))
    <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.destroy', [$team, $match]) }}"
          hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.confirm_delete_match') }}" class="self-start">
        @csrf
        <button type="submit" class="btn btn-error btn-outline">{{ __('kopling-sports-management::messages.delete_match') }}</button>
    </form>
@endif
