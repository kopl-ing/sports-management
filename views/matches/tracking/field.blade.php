@use('Kopling\SportsManagement\MatchState')
@use('Kopling\SportsManagement\Position')
@php
    $benchMembers = $members->reject(fn ($member) => array_key_exists($member->id, $placement));
    if ($state !== MatchState::Planned) {
        $benchMembers = $benchMembers->sortBy(fn ($member) => $played[$member->id] ?? 0);
    }
@endphp
<div data-sm-field @if ($canMove) data-sm-editable @endif @if ($state === MatchState::Live) data-sm-live @endif
     data-sm-max="{{ $maxOnField }}"
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
        </form>
    @endif

    <div class="relative flex items-center justify-between text-sm">
        <span class="opacity-60">{{ $state === MatchState::Planned ? __('kopling-sports-management::messages.lineup') : __('kopling-sports-management::messages.on_field') }}</span>
        <span class="tabular-nums opacity-60">{{ count($placement) }}{{ $maxOnField ? ' / '.$maxOnField : '' }}</span>
        @if ($canTrack && $state === MatchState::Live)
            <div class="hidden group-data-[sm-goal]:flex absolute inset-x-0 bottom-0 z-10 alert alert-success py-2 shadow-md">
                <span class="hidden group-data-[sm-goal=scorer]:inline">{{ __('kopling-sports-management::messages.tap_scorer') }}</span>
                <span class="hidden group-data-[sm-goal=assist]:inline">{{ __('kopling-sports-management::messages.tap_assist') }}</span>
                <div class="ms-auto flex gap-1">
                    <button type="button" data-sm-goal-skip class="btn btn-sm hidden group-data-[sm-goal=assist]:inline-flex">{{ __('kopling-sports-management::messages.skip') }}</button>
                    <button type="button" data-sm-goal-cancel class="btn btn-sm btn-ghost">{{ __('kopling-sports-management::messages.cancel') }}</button>
                </div>
            </div>
        @endif
    </div>

    <div class="card card-border bg-base-100 flex-1 overflow-hidden">
        @foreach ([Position::Forward, Position::Midfield, Position::Defender, Position::Keeper] as $zone)
            <div data-sm-zone="{{ $zone->value }}"
                 class="relative flex-1 flex flex-wrap items-center justify-center gap-5 p-2 border-b border-dashed border-base-300 last:border-b-0 data-[sm-over]:bg-primary/10 data-[sm-refused]:bg-error/15">
                <span class="absolute start-3 top-2 text-xs font-semibold opacity-40" title="{{ $zone->label() }}">{{ $zone->value }}</span>
                @foreach ($members->filter(fn ($member) => ($placement[$member->id] ?? false) === $zone) as $member)
                    @include('kopling-sports-management::matches.tracking.player')
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
        </form>
        <button type="button" data-sm-goal-start class="btn btn-success btn-lg w-full group-data-[sm-goal]:btn-disabled">
            {{ __('kopling-sports-management::messages.goal_for_us') }}
        </button>
    @endif
</div>
