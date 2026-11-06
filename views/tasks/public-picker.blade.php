@extends('kopling-sports-management::layouts.live')
@section('title', __('kopling-sports-management::messages.tasks.heading').' · '.$teamName)

@section('content')
    <section class="card card-border bg-base-100"
             x-data="{
                 key: @js($storageKey),
                 urls: @js(collect($players)->pluck('url')->all()),
                 init() {
                     try {
                         @if ($pick) localStorage.removeItem(this.key); @else const url = localStorage.getItem(this.key); if (this.urls.includes(url)) location.replace(url); @endif
                     } catch (e) {}
                 },
                 remember(url) {
                     try { localStorage.setItem(this.key, url) } catch (e) {}
                 },
             }">
        <div class="card-body items-center text-center gap-4">
            <h1 class="text-xl font-semibold">{{ $teamName }}</h1>
            <p class="opacity-60">{{ __('kopling-sports-management::messages.tasks.link.pick') }}</p>
            @if ($players === [])
                <p class="opacity-60">{{ __('kopling-sports-management::messages.no_members') }}</p>
            @else
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-4 w-full">
                    @foreach ($players as $player)
                        <a href="{{ $player['url'] }}" class="flex flex-col items-center gap-1 rounded-box p-2 hover:bg-base-200"
                           x-on:click="remember($el.href)">
                            <x-k::person.avatar :name="$player['name']" :color="$player['color']" :mask="null" size="w-14" />
                            <span class="text-sm">{{ $player['name'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
