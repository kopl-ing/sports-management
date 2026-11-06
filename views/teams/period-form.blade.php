@php
    $filled = $reopening === $formId;
    [$seasonStart, $seasonEnd] = $team->seasonBounds();
@endphp
<form method="POST" action="{{ $action }}" class="flex flex-col gap-4" autocomplete="off">
    @csrf
    <input type="hidden" name="_form" value="{{ $formId }}">
    <h2 class="text-lg font-semibold">{{ $title }}</h2>
    <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-sports-management::messages.periods.name'), 'value' => $filled ? old('name') : $period?->name, 'required' => true]" />
    <div class="grid grid-cols-2 gap-4">
        <x-k::form.input :data="['name' => 'starts_on', 'label' => __('kopling-sports-management::messages.tasks.starts_on'), 'type' => 'date', 'value' => $filled ? old('starts_on') : ($period?->starts_on ?? $seasonStart)->toDateString(), 'required' => true]" />
        <x-k::form.input :data="['name' => 'ends_on', 'label' => __('kopling-sports-management::messages.tasks.ends_on'), 'type' => 'date', 'value' => $filled ? old('ends_on') : ($period?->ends_on ?? $seasonEnd)->toDateString(), 'required' => true]" />
    </div>
    @if ($filled && $errors->any())
        <p class="text-error text-sm">{{ $errors->first() }}</p>
    @endif
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('kopling-sports-management::messages.save') }}</button>
        <x-k::modal.cancel />
    </div>
</form>
