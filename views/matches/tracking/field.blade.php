@use('Kopling\SportsManagement\MatchState')
@use('Kopling\SportsManagement\FieldSlots')
@php
    $benchMembers = $members->reject(fn ($member) => array_key_exists($member->id, $placement));
    if ($state !== MatchState::Planned) {
        $benchMembers = $benchMembers->sortBy(fn ($member) => $played[$member->id] ?? 0);
    }
@endphp
<div data-sm-field @if ($canMove) data-sm-editable @endif @if ($state === MatchState::Live) data-sm-live @endif
     data-sm-max="{{ $maxOnField }}" data-sm-keeper="{{ $keeperZone?->value }}"
     class="group flex flex-col gap-2 h-[calc(100dvh-10rem)] min-h-[28rem]">
    @if ($canMove)
        <form autocomplete="off" data-sm-move method="POST" hx-boost="true" class="hidden"
              action="{{ $state === MatchState::Planned
                  ? route('kopling-sports-management::sports-management/matches.lineup', [$team, $match])
                  : route('kopling-sports-management::sports-management/matches.field', [$team, $match]) }}">
            @csrf
            <input type="hidden" name="team_member_id">
            <input type="hidden" name="zone">
            <input type="hidden" name="replace_team_member_id">
            <input type="hidden" name="before_team_member_id">
        </form>
    @endif

    <div class="relative flex items-center justify-between text-sm">
        <span class="opacity-60">{{ $state === MatchState::Planned ? __('kopling-sports-management::messages.lineup') : $team->sport->trans('on_field') }}</span>
        <span class="tabular-nums opacity-60">{{ count($placement) }}{{ $maxOnField ? ' / '.$maxOnField : '' }}</span>
        @if ($canTrack && $state === MatchState::Live)
            <div class="hidden group-data-[sm-goal]:flex absolute inset-x-0 bottom-0 z-10 alert alert-success py-2 shadow-md">
                <span class="hidden group-data-[sm-goal=scorer]:inline">{{ $team->sport->trans('tap_scorer') }}</span>
                <span class="hidden group-data-[sm-goal=assist]:inline">{{ __('kopling-sports-management::messages.tap_assist') }}</span>
                <div class="ms-auto flex gap-1">
                    @if ($pointValues === [1])
                        <button type="button" data-sm-goal-own class="btn btn-sm hidden group-data-[sm-goal=scorer]:inline-flex">{{ __('kopling-sports-management::messages.own_goal') }}</button>
                    @endif
                    <button type="button" data-sm-goal-skip class="btn btn-sm hidden group-data-[sm-goal=assist]:inline-flex">{{ __('kopling-sports-management::messages.skip') }}</button>
                    <button type="button" data-sm-goal-cancel class="btn btn-sm btn-ghost">{{ __('kopling-sports-management::messages.cancel') }}</button>
                </div>
            </div>
            @if ($sanctionKinds !== [])
                <div class="hidden group-data-[sm-sanction]:flex absolute inset-x-0 bottom-0 z-10 alert alert-warning py-2 shadow-md">
                    <span class="hidden group-data-[sm-sanction=player]:inline">{{ __('kopling-sports-management::messages.tap_sanctioned') }}</span>
                    <div class="ms-auto flex flex-wrap justify-end gap-1">
                        @foreach ($sanctionKinds as $kind)
                            <button type="button" data-sm-sanction-kind="{{ $kind->value }}" class="btn btn-sm hidden group-data-[sm-sanction=kind]:inline-flex">{{ $kind->label() }}</button>
                        @endforeach
                        <button type="button" data-sm-sanction-cancel class="btn btn-sm btn-ghost">{{ __('kopling-sports-management::messages.cancel') }}</button>
                    </div>
                </div>
            @endif
        @endif
    </div>

    @if ($canMove && $state === MatchState::Live)
        @foreach (array_unique([...array_keys($ticking ? $penaltyLeft : []), ...$awaitingReturn]) as $memberId)
            <div role="status" class="alert alert-info py-2" @if (! in_array($memberId, $awaitingReturn, true)) hidden @endif
                 @if (array_key_exists($memberId, $penaltyLeft) && $ticking)
                     x-data x-init="setTimeout(() => {
                         $el.hidden = false;
                         navigator.vibrate?.([300, 150, 300]);
                         const field = $el.closest('[data-sm-field]');
                         field.dataset.smMax = Number(field.dataset.smMax) + 1;
                         field.querySelector('[data-sm-player=&quot;{{ $memberId }}&quot;]')?.removeAttribute('data-sm-unavailable');
                     }, {{ $penaltyLeft[$memberId] * 1000 }})"
                 @endif>
                <span>{{ __('kopling-sports-management::messages.penalty_over', ['name' => $members->get($memberId)?->person->name ?? '?']) }}</span>
                <div class="ms-auto flex gap-1">
                    <button type="button" data-sm-select-player="{{ $memberId }}" class="btn btn-sm">{{ __('kopling-sports-management::messages.bring_back') }}</button>
                    <button type="button" class="btn btn-sm btn-ghost" x-data x-on:click="$el.closest('[role=status]').remove()">{{ __('kopling-sports-management::messages.dismiss') }}</button>
                </div>
            </div>
        @endforeach
    @endif

    <div class="card card-border bg-base-100 flex-1 overflow-hidden">
        @foreach ($zones as $zone)
            <div data-sm-zone="{{ $zone->value }}"
                 class="relative flex-1 flex flex-wrap items-center justify-center gap-5 p-2 border-b border-dashed border-base-300 last:border-b-0 data-[sm-over]:bg-primary/10 data-[sm-refused]:bg-error/15">
                <span class="absolute start-3 top-2 text-xs font-semibold opacity-40" title="{{ $zone->label($team->sport) }}">{{ $zone->value }}</span>
                @foreach (FieldSlots::order($members->filter(fn ($member) => ($placement[$member->id] ?? false) === $zone)->keys()->all(), $slots) as $memberId)
                    @include('kopling-sports-management::matches.tracking.player', ['member' => $members[$memberId]])
                @endforeach
            </div>
        @endforeach
    </div>

    <div data-sm-bench class="relative card card-border bg-base-200 data-[sm-over]:bg-primary/10">
        @if ($fairShare !== null)
            <span class="px-3 pt-2 text-center text-xs font-semibold opacity-40 tabular-nums pointer-events-none"
                  title="{{ __('kopling-sports-management::messages.target_help') }}">{{ __('kopling-sports-management::messages.target_play_time', ['minutes' => intdiv($fairShare, 60)]) }}@if ($fairBench), {{ __('kopling-sports-management::messages.target_bench_time', ['minutes' => intdiv($fairBench, 60)]) }}@endif</span>
        @endif
        <div data-sm-bench-list class="card-body flex-row flex-wrap items-center justify-center gap-5 p-3 min-h-24">
            @forelse ($benchMembers as $member)
                @include('kopling-sports-management::matches.tracking.player')
            @empty
                <span class="text-sm opacity-60">{{ __('kopling-sports-management::messages.bench_empty') }}</span>
            @endforelse
        </div>
    </div>

    @if ($canTrack && $state === MatchState::Live)
        <form autocomplete="off" data-sm-goal-form method="POST" hx-boost="true" class="hidden"
              action="{{ route('kopling-sports-management::sports-management/matches.goals.store', [$team, $match]) }}">
            @csrf
            <input type="hidden" name="scorer_team_member_id">
            <input type="hidden" name="assist_team_member_id">
            <input type="hidden" name="own_goal">
            <input type="hidden" name="points" value="1">
        </form>
        <div class="flex gap-2">
            @foreach ($pointValues as $points)
                <button type="button" data-sm-goal-start data-sm-points="{{ $points }}" class="btn btn-success btn-lg flex-1 group-data-[sm-goal]:btn-disabled group-data-[sm-sanction]:btn-disabled"
                        @if (count($pointValues) > 1) aria-label="{{ trans_choice('kopling-sports-management::messages.points_for_us', $points) }}" @endif>
                    {{ count($pointValues) > 1 ? '+'.$points : $team->sport->trans('goal_for_us') }}
                </button>
            @endforeach
            @if ($sanctionKinds !== [])
                <form autocomplete="off" data-sm-sanction-form method="POST" hx-boost="true" class="hidden"
                      action="{{ route('kopling-sports-management::sports-management/matches.sanctions.store', [$team, $match]) }}">
                    @csrf
                    <input type="hidden" name="team_member_id">
                    <input type="hidden" name="kind">
                </form>
                <button type="button" data-sm-sanction-start class="btn btn-warning btn-lg group-data-[sm-goal]:btn-disabled group-data-[sm-sanction]:btn-disabled">
                    {{ __('kopling-sports-management::messages.sanction_button.'.$team->sport->value) }}
                </button>
            @endif
        </div>
    @endif
</div>
