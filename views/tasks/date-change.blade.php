<form method="POST" action="{{ route('kopling-sports-management::sports-management/tasks.date', [$team, $enlistment]) }}" class="ms-auto">
    @csrf
    <input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">
    <input type="hidden" name="action" value="{{ $action }}">
    <button type="submit" class="btn btn-xs btn-ghost">{{ __('kopling-sports-management::messages.tasks.'.($action === 'skip' ? 'skip_date' : 'restore_date')) }}</button>
</form>
