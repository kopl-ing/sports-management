@php
    $undo = session(\Kopling\SportsManagement\Controllers\TrackingController::undoKey($match));
    $undoLeft = $undo ? \Kopling\SportsManagement\Controllers\TrackingController::UNDO_SECONDS - (now()->timestamp - $undo['at']) : 0;
@endphp
@if ($canTrack && $undoLeft > 0)
    <div class="toast toast-top toast-center top-20 z-40" x-data x-init="setTimeout(() => $el.remove(), {{ $undoLeft * 1000 }})">
        <div class="alert">
            <span>{{ __($undo['goals'] ? 'kopling-sports-management::messages.recorded_goal' : 'kopling-sports-management::messages.recorded_change') }}</span>
            <form autocomplete="off" method="POST" action="{{ route('kopling-sports-management::sports-management/matches.undo', [$team, $match]) }}" hx-boost="true">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary">{{ __('kopling-sports-management::messages.undo') }}</button>
            </form>
        </div>
    </div>
@endif
