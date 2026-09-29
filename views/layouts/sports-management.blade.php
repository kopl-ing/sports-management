<x-k::community.chrome
    portal-id="kopling-sports-management::sports-management"
    topbar-slot="kopling-sports-management::sports-management.topbar"
    topbar-start-slot="kopling-sports-management::sports-management.topbar-start"
    :sidebar-slot="($sidebar ?? true) ? 'kopling-sports-management::sports-management.sidebar-panel' : null"
    :rail-slot="null"
    :composer-slot="null"
    :mobile-dock="false"
    :show-label="$label ?? true"
    main-class=""
>
    @yield('content')
</x-k::community.chrome>
