@if ($compact ?? false)
    <button type="button" class="btn btn-sm btn-ghost" x-data="{ copied: false }"
            x-on:click="navigator.clipboard.writeText(@js($url)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
            x-text="copied ? @js(__('kopling-sports-management::messages.public.copied')) : @js(__('kopling-sports-management::messages.tasks.link.copy'))">{{ __('kopling-sports-management::messages.tasks.link.copy') }}</button>
@else
    {{-- Same copy field as the public link on matches/show. --}}
    <div class="join w-full" x-data="{ copied: false }">
        <input type="text" readonly value="{{ $url }}" x-ref="url" x-on:focus="$el.select()"
               class="input join-item flex-1 min-w-0" aria-label="{{ __('kopling-sports-management::messages.tasks.link.label') }}">
        <button type="button" class="btn join-item"
                x-on:click="navigator.clipboard.writeText($refs.url.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                x-text="copied ? @js(__('kopling-sports-management::messages.public.copied')) : @js(__('kopling-sports-management::messages.public.copy'))">{{ __('kopling-sports-management::messages.public.copy') }}</button>
    </div>
@endif
