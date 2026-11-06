@if ($canManageTeam)
    <x-k::modal :label="__('kopling-sports-management::messages.edit')" :id="$modalId">
        <x-slot:trigger>{{ __('kopling-sports-management::messages.edit') }}</x-slot:trigger>
        <form method="POST" action="{{ route('kopling-sports-management::sports-management/teams.members.update', [$team, $member]) }}" class="flex flex-col gap-4">
            @csrf
            <input type="hidden" name="_form" value="{{ $modalId }}">
            <h2 class="text-lg font-semibold">{{ $member->person->name }}</h2>
            <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-sports-management::messages.name'), 'value' => $reopening === $modalId ? old('name') : $member->person->name]" />
            <x-k::form.input :data="['name' => 'jersey_number', 'label' => __('kopling-sports-management::messages.jersey_number'), 'value' => $reopening === $modalId ? old('jersey_number') : $member->jersey_number]" />
            <x-k::form.multi-select :data="['name' => 'positions', 'label' => __('kopling-sports-management::messages.positions_label'), 'options' => $positions, 'value' => $reopening === $modalId ? old('positions', []) : ($member->positions?->pluck('value') ?? [])]" />
            <x-k::form.toggle :data="['name' => 'guest', 'label' => __('kopling-sports-management::messages.guest'), 'value' => $reopening === $modalId ? old('guest') : $member->guest]" />
            <x-k::form.input :data="['name' => 'left_on', 'label' => __('kopling-sports-management::messages.left_on'), 'type' => 'date', 'description' => __('kopling-sports-management::messages.left_on_help'), 'value' => $reopening === $modalId ? old('left_on') : $member->left_on?->toDateString()]" />
            @if ($reopening === $modalId && $errors->any())
                <p class="text-error text-sm">{{ $errors->first() }}</p>
            @endif
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('kopling-sports-management::messages.save') }}</button>
                <x-k::modal.cancel />
            </div>
        </form>
        <div class="flex flex-wrap gap-2 mt-6 pt-4 border-t border-base-300">
            @if ($playerLinks && ! $member->guest && ! $member->hasLeft())
                <form method="POST" action="{{ route('kopling-sports-management::sports-management/tasks.member-link', [$team, $member]) }}"
                      hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.tasks.link.confirm_regenerate') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm">{{ __('kopling-sports-management::messages.tasks.link.regenerate') }}</button>
                </form>
            @endif
            <form method="POST" action="{{ route('kopling-sports-management::sports-management/teams.members.destroy', [$team, $member]) }}"
                  hx-boost="true" hx-confirm="{{ __('kopling-sports-management::messages.confirm_delete_member') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-sports-management::messages.delete') }}</button>
            </form>
        </div>
    </x-k::modal>
@endif
