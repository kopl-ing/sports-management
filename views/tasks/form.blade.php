@php
    $filled = $reopening === $formId;
    $rule = $task?->recurrenceRule();
    $hhmm = fn (?string $time) => $time === null ? '' : substr($time, 0, 5);
    $values = $filled ? old() : [
        'schedule' => $task?->schedule->value,
        'lead_minutes' => $task?->lead_minutes,
        'dates' => $task?->dates->map(fn ($date) => ['date' => $date->date->toDateString(), 'time' => $hhmm($date->time)])->all(),
        'frequency' => $rule?->frequency->value,
        'interval' => $rule?->interval,
        'weekday' => $rule?->weekday,
        'monthly_by' => $rule?->nth === null ? 'day' : 'nth',
        'day' => $rule?->day,
        'nth' => $rule?->nth,
        'month' => $rule?->month,
        'time' => $hhmm($task?->time),
        'starts_on' => $task?->starts_on?->toDateString(),
        'ends_on' => $task?->ends_on?->toDateString(),
        'within_periods' => $task?->within_periods ? '1' : '0',
        'enrolment' => $task?->enrolment->value,
    ];
    $field = fn (string $key, $current) => $filled ? old($key) : $current;
@endphp
<form method="POST" action="{{ $action }}" class="flex flex-col gap-4" autocomplete="off">
    @csrf
    <input type="hidden" name="_form" value="{{ $formId }}">
    <h2 class="text-lg font-semibold">{{ $title }}</h2>
    <x-k::form.input :data="['name' => 'title', 'label' => __('kopling-sports-management::messages.tasks.title'), 'value' => $field('title', $task?->title), 'required' => true]" />
    <x-k::form.text-area :data="['name' => 'description', 'label' => __('kopling-sports-management::messages.tasks.description'), 'rows' => 3, 'value' => $field('description', $task?->description)]" />
    @include('kopling-sports-management::tasks.schedule-fields', ['values' => $values])
    <div class="grid grid-cols-2 gap-4">
        <x-k::form.input :data="['name' => 'min_people', 'label' => __('kopling-sports-management::messages.tasks.min_people'), 'type' => 'number', 'value' => $field('min_people', $task?->min_people)]" />
        <x-k::form.input :data="['name' => 'max_people', 'label' => __('kopling-sports-management::messages.tasks.max_people'), 'type' => 'number', 'value' => $field('max_people', $task?->max_people)]" />
    </div>
    <div class="grid grid-cols-2 gap-4">
        <x-k::form.input :data="['name' => 'effort_minutes', 'label' => __('kopling-sports-management::messages.tasks.effort_minutes'), 'type' => 'number', 'value' => $field('effort_minutes', $task?->effort_minutes)]" />
        <x-k::form.input :data="['name' => 'effort_amount', 'label' => __('kopling-sports-management::messages.tasks.effort_amount'), 'type' => 'number', 'value' => $field('effort_amount', $task?->effort_cents === null ? null : $task->effort_cents / 100)]" />
    </div>
    <x-k::form.toggle :data="['name' => 'staff_only', 'label' => __('kopling-sports-management::messages.tasks.staff_only'), 'description' => __('kopling-sports-management::messages.tasks.staff_only_help'), 'value' => $field('staff_only', $task?->staff_only)]" />
    <x-k::form.select :data="['name' => 'undo_window', 'label' => __('kopling-sports-management::messages.tasks.undo_window_label'), 'options' => \Kopling\SportsManagement\TaskUndoWindow::options(), 'description' => __('kopling-sports-management::messages.tasks.undo_window_help'), 'value' => (string) $field('undo_window', $task?->undo_window->value ?? 'any_time'), 'required' => true]" />
    @if ($team->tasks_approve_completion)
        <x-k::form.toggle :data="['name' => 'confirm_each_occurrence', 'label' => __('kopling-sports-management::messages.tasks.confirm_each'), 'description' => __('kopling-sports-management::messages.tasks.confirm_each_help'), 'value' => $field('confirm_each_occurrence', $task?->confirm_each_occurrence)]" />
    @endif
    <x-k::form.input :data="['name' => 'weight', 'label' => __('kopling-sports-management::messages.tasks.weight'), 'type' => 'number', 'description' => __('kopling-sports-management::messages.tasks.weight_help'), 'value' => $field('weight', $task?->weight ?? 1), 'required' => true]" />
    @if ($filled && $errors->any())
        <p class="text-error text-sm">{{ $errors->first() }}</p>
    @endif
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('kopling-sports-management::messages.save') }}</button>
        <x-k::modal.cancel />
    </div>
</form>
