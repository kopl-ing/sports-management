@extends('kopling-moderation::layouts.moderation')

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-bold">{{ __('kopling-sports-management::messages.teams') }}</h1>
            <div role="tablist" class="tabs tabs-box tabs-sm" hx-boost:inherited="true">
                @foreach (\Kopling\SportsManagement\Controllers\ModerationController::SORTS as $option)
                    <a role="tab" href="{{ route('kopling-moderation::moderation/kopling-sports-management.teams', ['sort' => $option]) }}"
                       class="tab @if ($sort === $option) tab-active @endif">{{ __("kopling-sports-management::messages.moderation.sort.$option") }}</a>
                @endforeach
            </div>
        </div>
        <p class="text-sm opacity-60">{{ __('kopling-sports-management::messages.moderation.overview_help') }}</p>

        <div id="sm-moderation-teams-wrapper">
            <div class="flex flex-col gap-4">
                @forelse ($context->getSubjectPaginator() as $team)
                    <div class="card card-border bg-base-100">
                        <div class="card-body gap-3">
                            @include('kopling-sports-management::moderation.team-summary')

                            {{-- Plain POSTs, same as the moderation queue's own row actions. --}}
                            <div class="flex items-center justify-end gap-2">
                                @if ($team->trashed())
                                    <form method="POST" action="{{ route('kopling-core::community/flag.unhide', ['type' => $team->getMorphClass(), 'id' => $team->id]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline btn-sm">{{ __('kopling-moderation::moderation.unhide') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('kopling-core::community/flag.hide', ['type' => $team->getMorphClass(), 'id' => $team->id]) }}"
                                          onsubmit="return confirm(@js(__('kopling-sports-management::messages.moderation.confirm_hide')))">
                                        @csrf
                                        <button type="submit" class="btn btn-error btn-sm">{{ __('kopling-moderation::moderation.hide') }}</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('kopling-core::community/flag.destroy', ['type' => $team->getMorphClass(), 'id' => $team->id]) }}"
                                      onsubmit="return confirm(@js(__('kopling-sports-management::messages.moderation.confirm_delete')))">
                                    @csrf
                                    <button type="submit" class="btn btn-error btn-outline btn-sm">{{ __('kopling-moderation::moderation.delete') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="opacity-60">{{ __('kopling-sports-management::messages.no_teams') }}</p>
                @endforelse
            </div>
            <x-k::page.pagination :context="$context" target="#sm-moderation-teams-wrapper" />
        </div>
    </div>
@endsection
