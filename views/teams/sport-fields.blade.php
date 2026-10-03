@use('Kopling\SportsManagement\Sport')
@use('Kopling\SportsManagement\TeamFormatPreset')
<div class="flex flex-col gap-4"
     @if ($sportEditable)
         hx-get="{{ route('kopling-sports-management::sports-management/teams.sport-fields') }}" hx-trigger="change"
         hx-include="closest form" hx-target="this" hx-swap="outerHTML"
     @endif>
    @if ($sportEditable)
        <x-k::form.select :data="['name' => 'sport', 'label' => __('kopling-sports-management::messages.sport'), 'options' => Sport::options(), 'value' => $sport->value, 'required' => true]" />
    @else
        <fieldset class="fieldset">
            <legend class="fieldset-legend">{{ __('kopling-sports-management::messages.sport') }}</legend>
            <p>{{ $sport->label() }}</p>
            <p class="label">{{ __('kopling-sports-management::messages.sport_locked') }}</p>
        </fieldset>
    @endif
    <x-k::form.select :data="['name' => 'format_preset_id', 'label' => __('kopling-sports-management::messages.format_preset'), 'options' => TeamFormatPreset::options($sport), 'value' => $presetId]" />
</div>
