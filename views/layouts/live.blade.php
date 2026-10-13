<x-k::community.chrome
    portal-id="kopling-sports-management::live"
    topbar-slot="kopling-sports-management::live.topbar"
    :sidebar-slot="null"
    :rail-slot="null"
    :composer-slot="null"
    :mobile-dock="false"
    main-class="max-w-2xl mx-auto"
>
    @yield('content')
</x-k::community.chrome>
