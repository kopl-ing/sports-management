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
    $playedRange = $state === MatchState::Planned ? null : $members->keys()->map(fn ($id) => $played[$id] ?? 0);
    $fairShare = $state === MatchState::Planned ? null : $match->fairShareSeconds($members->count());
    $fairBench = $state === MatchState::Planned ? null : ($match->fairBenchSeconds($members->count()) ?: null);
    $matchSeconds = $timeline->matchSeconds();
    $fairShareStyle = '--badge-color: var(--color-success); --badge-fg: var(--color-success-content)';
    $playedTint = function (string $memberId) use ($playedRange, $played, $fairShare, $fairShareStyle): string {
        if ($fairShare !== null && ($played[$memberId] ?? 0) >= $fairShare) {
            return $fairShareStyle;
        }
        $min = $playedRange?->min();
        $max = $playedRange?->max();
        if ($playedRange === null || $max === $min) {
            return '';
        }
        $share = (($played[$memberId] ?? 0) - $min) / ($max - $min);
        [$color, $strength] = $share < 0.5 ? ['warning', 1 - 2 * $share] : ['info', 2 * $share - 1];
        $fg = $strength >= 0.5 ? "var(--color-{$color}-content)" : 'var(--color-base-content)';

        return sprintf('--badge-color: color-mix(in oklab, var(--color-%s) %d%%, var(--color-base-100)); --badge-fg: %s', $color, round($strength * 100), $fg);
    };
    $name = fn (?string $memberId) => $memberId ? ($members->get($memberId)?->person->name ?? '?') : __('kopling-sports-management::messages.scorer_unknown');
@endphp

@section('content')
    {{-- Every action redirects back here; replace instead of push so Back leaves the match screen. --}}
    <div hx-replace-url:inherited="true" class="max-w-3xl flex flex-col gap-3">
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
