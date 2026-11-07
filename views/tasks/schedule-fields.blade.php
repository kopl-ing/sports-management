@use('Kopling\SportsManagement\TaskSchedule')
@use('Kopling\SportsManagement\TaskFrequency')
@use('Kopling\SportsManagement\TaskRecurrence')
@php
    [$seasonStart, $seasonEnd] = $team->seasonBounds();
    $v = fn (string $key, $default = '') => (string) ($values[$key] ?? $default);
    $schedule = TaskSchedule::tryFrom($v('schedule')) ?? TaskSchedule::AllMatches;
    $frequency = TaskFrequency::tryFrom($v('frequency')) ?? TaskFrequency::Weekly;
    $byNth = $v('monthly_by') === 'nth';
    $weekdays = collect(range(1, 7))->mapWithKeys(fn (int $day) => [$day => now()->startOfWeek()->addDays($day - 1)->translatedFormat('l')])->all();
    $months = collect(range(1, 12))->mapWithKeys(fn (int $month) => [$month => now()->setDate(2000, $month, 1)->translatedFormat('F')])->all();
    $nths = collect([1, 2, 3, 4, TaskRecurrence::NTH_LAST])->mapWithKeys(fn (int $nth) => [$nth => __('kopling-sports-management::messages.tasks.summary.nth.'.$nth)])->all();
    $dates = array_values((array) ($values['dates'] ?? [])) ?: [['date' => '', 'time' => '']];
    $repeats = $schedule !== TaskSchedule::Dates || collect($dates)->filter(fn ($row) => ($row['date'] ?? '') !== '')->count() > 1;
    $refresh = route('kopling-sports-management::sports-management/tasks.schedule-fields', $team);
@endphp
<div class="flex flex-col gap-4" data-schedule-fields
     hx-get="{{ $refresh }}" hx-trigger="change[target.tagName=='SELECT'||target.type=='date']"
     hx-include="closest form" hx-target="this" hx-swap="outerHTML">
    <x-k::form.select :data="['name' => 'schedule', 'label' => __('kopling-sports-management::messages.tasks.schedule_label'), 'options' => TaskSchedule::options(), 'value' => $schedule->value, 'required' => true]" />

    @if ($schedule->isMatchBound())
        <x-k::form.input :data="['name' => 'lead_minutes', 'label' => __('kopling-sports-management::messages.tasks.lead_minutes'), 'type' => 'number', 'min' => 0, 'max' => 1440, 'description' => __('kopling-sports-management::messages.tasks.lead_minutes_help'), 'value' => $v('lead_minutes')]" />
    @elseif ($schedule === TaskSchedule::Dates)
        <fieldset class="fieldset">
            <legend class="fieldset-legend">{{ __('kopling-sports-management::messages.tasks.dates_label') }}</legend>
            @foreach ($dates as $i => $row)
                <div class="flex gap-2">
                    <input type="date" name="dates[{{ $i }}][date]" value="{{ $row['date'] ?? '' }}" class="input" aria-label="{{ __('kopling-sports-management::messages.tasks.date') }}">
                    <input type="time" name="dates[{{ $i }}][time]" value="{{ $row['time'] ?? '' }}" class="input w-32" aria-label="{{ __('kopling-sports-management::messages.tasks.time') }}">
                </div>
            @endforeach
            <p class="label whitespace-normal">{{ __('kopling-sports-management::messages.tasks.dates_help') }}</p>
            <button type="button" class="btn btn-sm self-start"
                    hx-get="{{ $refresh }}" hx-vals='{"add_date": 1}' hx-include="closest form"
                    hx-target="closest [data-schedule-fields]" hx-swap="outerHTML">
                {{ __('kopling-sports-management::messages.tasks.add_date') }}
            </button>
        </fieldset>
    @elseif ($schedule === TaskSchedule::Season)
        <p class="text-sm opacity-60">{{ __('kopling-sports-management::messages.tasks.season_help') }}</p>
        <div class="grid grid-cols-2 gap-4">
            <x-k::form.input :data="['name' => 'starts_on', 'label' => __('kopling-sports-management::messages.tasks.starts_on'), 'type' => 'date', 'value' => $v('starts_on', $seasonStart->toDateString()), 'required' => true]" />
            <x-k::form.input :data="['name' => 'ends_on', 'label' => __('kopling-sports-management::messages.tasks.ends_on'), 'type' => 'date', 'value' => $v('ends_on', $seasonEnd->toDateString()), 'required' => true]" />
        </div>
        <x-k::form.toggle :data="['name' => 'within_periods', 'label' => __('kopling-sports-management::messages.periods.within'), 'description' => $team->periods->isEmpty() ? __('kopling-sports-management::messages.periods.within_none') : __('kopling-sports-management::messages.periods.within_help', ['periods' => $team->periods->pluck('name')->join(', ')]), 'value' => $v('within_periods') === '1']" />
    @else
        <x-k::form.select :data="['name' => 'frequency', 'label' => __('kopling-sports-management::messages.tasks.frequency_label'), 'options' => TaskFrequency::options(), 'value' => $frequency->value]" />
        @if ($frequency !== TaskFrequency::Yearly)
            <x-k::form.input :data="['name' => 'interval', 'label' => __('kopling-sports-management::messages.tasks.interval.'.$frequency->value), 'type' => 'number', 'min' => 1, 'max' => 52, 'value' => $v('interval', 1)]" />
        @endif
        @if ($frequency === TaskFrequency::Weekly)
            <x-k::form.select :data="['name' => 'weekday', 'label' => __('kopling-sports-management::messages.tasks.weekday'), 'options' => $weekdays, 'value' => $v('weekday', 6)]" />
        @elseif ($frequency === TaskFrequency::Monthly)
            <x-k::form.select :data="['name' => 'monthly_by', 'label' => __('kopling-sports-management::messages.tasks.monthly_by_label'), 'options' => ['day' => __('kopling-sports-management::messages.tasks.monthly_by.day'), 'nth' => __('kopling-sports-management::messages.tasks.monthly_by.nth')], 'value' => $byNth ? 'nth' : 'day']" />
            @if ($byNth)
                <div class="grid grid-cols-2 gap-4">
                    <x-k::form.select :data="['name' => 'nth', 'label' => __('kopling-sports-management::messages.tasks.nth'), 'options' => $nths, 'value' => $v('nth', 1)]" />
                    <x-k::form.select :data="['name' => 'weekday', 'label' => __('kopling-sports-management::messages.tasks.weekday'), 'options' => $weekdays, 'value' => $v('weekday', 6)]" />
                </div>
            @else
                <x-k::form.input :data="['name' => 'day', 'label' => __('kopling-sports-management::messages.tasks.day'), 'type' => 'number', 'min' => 1, 'max' => 31, 'description' => __('kopling-sports-management::messages.tasks.day_help'), 'value' => $v('day', 1)]" />
            @endif
        @else
            <div class="grid grid-cols-2 gap-4">
                <x-k::form.input :data="['name' => 'day', 'label' => __('kopling-sports-management::messages.tasks.day'), 'type' => 'number', 'min' => 1, 'max' => 31, 'value' => $v('day', 1)]" />
                <x-k::form.select :data="['name' => 'month', 'label' => __('kopling-sports-management::messages.tasks.month'), 'options' => $months, 'value' => $v('month', 9)]" />
            </div>
        @endif
        <x-k::form.input :data="['name' => 'time', 'label' => __('kopling-sports-management::messages.tasks.time'), 'type' => 'time', 'value' => $v('time')]" />
        <div class="grid grid-cols-2 gap-4">
            <x-k::form.input :data="['name' => 'starts_on', 'label' => __('kopling-sports-management::messages.tasks.starts_on'), 'type' => 'date', 'value' => $v('starts_on', $seasonStart->toDateString()), 'required' => true]" />
            <x-k::form.input :data="['name' => 'ends_on', 'label' => __('kopling-sports-management::messages.tasks.ends_on'), 'type' => 'date', 'value' => $v('ends_on', $seasonEnd->toDateString()), 'required' => true]" />
        </div>
        <x-k::form.toggle :data="['name' => 'within_periods', 'label' => __('kopling-sports-management::messages.periods.within'), 'description' => $team->periods->isEmpty() ? __('kopling-sports-management::messages.periods.within_none') : __('kopling-sports-management::messages.periods.within_help', ['periods' => $team->periods->pluck('name')->join(', ')]), 'value' => $v('within_periods') === '1']" />
    @endif

    @if ($schedule === TaskSchedule::Season)
        <input type="hidden" name="enrolment" value="whole_task">
    @elseif ($repeats)
        <x-k::form.select :data="['name' => 'enrolment', 'label' => __('kopling-sports-management::messages.tasks.enrolment_label'), 'options' => \Kopling\SportsManagement\TaskEnrolment::options(), 'description' => __('kopling-sports-management::messages.tasks.enrolment_help'), 'value' => $v('enrolment', 'per_occurrence')]" />
    @else
        <input type="hidden" name="enrolment" value="per_occurrence">
    @endif
</div>
