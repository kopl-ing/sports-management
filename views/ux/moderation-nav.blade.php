{{-- Same boosted-without-target menu as `kopling-moderation::ux.queue-nav`. --}}
<ul class="menu px-4 pb-4 w-full" hx-boost:inherited="true">
    <li class="menu-title">{{ __('kopling-sports-management::messages.moderation.title') }}</li>
    <li>
        <a href="{{ route('kopling-moderation::moderation/kopling-sports-management.teams') }}" class="@if ($active) menu-active @endif">
            {{ __('kopling-sports-management::messages.teams') }}
        </a>
    </li>
</ul>
