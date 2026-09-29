@extends('kopling-sports-management::layouts.sports-management', ['sidebar' => false, 'label' => false])
@use('Kopling\SportsManagement\MatchState')
@use('Kopling\SportsManagement\PeriodType')
@php
    $canTrack = Gate::allows('kopling-sports-management::track-matches');
    $state = $timeline->state();
    $canMove = $state === MatchState::Planned ? Gate::allows('kopling-sports-management::manage-matches') : $canTrack;
    $score = $timeline->score();
    $running = $timeline->runningPeriod();
    $played = $timeline->playedSeconds();
    $onField = $timeline->onField();
    $bench = $members->keys()->diff(array_keys($onField));
    $ticking = $running?->type === PeriodType::Play;
    $editable = $canMove;
    $clock = fn (int $seconds) => intdiv($seconds, 60).':'.sprintf('%02d', $seconds % 60);
    $minute = fn (int $seconds) => intdiv($seconds, 60)."'";
    $name = fn (?string $memberId) => $memberId ? ($members->get($memberId)?->person->name ?? '?') : __('kopling-sports-management::messages.scorer_unknown');
@endphp

@section('content')
    <div class="max-w-3xl flex flex-col gap-3">
        <div class="tabs tabs-box tabs-sm">
            <input type="radio" name="sm-tab" value="field" class="tab" aria-label="{{ __('kopling-sports-management::messages.field_tab') }}" checked>
            <div class="tab-content pt-3">
                <div class="flex flex-col gap-3">
                    @if ($errors->any())
                        <p role="alert" class="alert alert-error py-2 text-sm">{{ $errors->first() }}</p>
                    @endif
                    @include('kopling-sports-management::matches.tracking.field')
                </div>
            </div>

            <input type="radio" name="sm-tab" value="report" class="tab" aria-label="{{ __('kopling-sports-management::messages.report_tab') }}">
            <div class="tab-content pt-3">
                <div class="flex flex-col gap-8">
                    @include('kopling-sports-management::matches.tracking.report')
                </div>
            </div>
        </div>

        @include('kopling-sports-management::matches.tracking.undo')
    </div>
@endsection
