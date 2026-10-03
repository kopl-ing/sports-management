@if (count($pointValues) > 1)
    <select name="points" class="select select-sm w-24" aria-label="{{ __('kopling-sports-management::messages.points') }}">
        @foreach ($pointValues as $points)
            <option value="{{ $points }}">+{{ $points }}</option>
        @endforeach
    </select>
@endif
