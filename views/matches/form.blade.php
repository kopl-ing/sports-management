@php
    $filled = $reopening === $formId;
    $field = fn (string $key, $current) => $filled ? old($key) : $current;
    $presetOptions = ['' => $team->formatPreset
        ? __('kopling-sports-management::messages.team_default_preset', ['preset' => $team->formatPreset->name])
        : __('kopling-sports-management::messages.team_default_preset_none')] + \Kopling\SportsManagement\TeamFormatPreset::options($team->sport);
    $homeAwayOptions = collect(\Kopling\SportsManagement\HomeAway::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
@endphp
<form method="POST" action="{{ $action }}" class="flex flex-col gap-4">
    @csrf
    <input type="hidden" name="_form" value="{{ $formId }}">
    <h2 class="text-lg font-semibold">{{ $title }}</h2>
    <x-k::form.input :data="['name' => 'opponent_name', 'label' => __('kopling-sports-management::messages.opponent'), 'value' => $field('opponent_name', $match?->opponent_name)]" />
    <x-k::form.select :data="['name' => 'home_away', 'label' => __('kopling-sports-management::messages.home_away_label'), 'options' => $homeAwayOptions, 'value' => $field('home_away', $match?->home_away->value ?? 'home')]" />
    <x-k::form.input :data="['name' => 'scheduled_at', 'label' => __('kopling-sports-management::messages.scheduled_at'), 'type' => 'datetime-local', 'value' => $field('scheduled_at', $match?->scheduled_at?->format('Y-m-d\TH:i'))]" />
    <x-k::form.text-area :data="['name' => 'location_address', 'label' => __('kopling-sports-management::messages.location_address'), 'rows' => 2, 'value' => $field('location_address', $match?->location_address)]" />
    <x-k::form.select :data="['name' => 'format_preset_id', 'label' => __('kopling-sports-management::messages.format_preset'), 'options' => $presetOptions, 'value' => (string) $field('format_preset_id', $match?->format_preset_id)]" />
    <x-k::form.input :data="['name' => 'play_minutes', 'label' => __('kopling-sports-management::messages.play_minutes'), 'type' => 'number', 'description' => __('kopling-sports-management::messages.play_minutes_help'), 'value' => $field('play_minutes', $match?->play_minutes)]" />
    @if ($filled && $errors->any())
        <p class="text-error text-sm">{{ $errors->first() }}</p>
    @endif
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('kopling-sports-management::messages.save') }}</button>
        <x-k::modal.cancel />
    </div>
</form>
