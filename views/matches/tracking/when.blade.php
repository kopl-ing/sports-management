<input type="number" name="minute" min="0" max="240" class="input input-sm w-24"
       aria-label="{{ __('kopling-sports-management::messages.minute') }}"
       placeholder="{{ $running ? __('kopling-sports-management::messages.minute_now') : __('kopling-sports-management::messages.minute') }}"
       @required(! $running)>
