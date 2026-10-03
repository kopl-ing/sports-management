@php($minutesOnly ??= false)
@php($prefix = empty($prefix) ? '' : $prefix.' ')
{{-- Counts up from the server-computed elapsed time, so client clock skew doesn't matter. Always pass `ticking`: an inherited parent `$ticking` would otherwise win. --}}
<span class="{{ $class ?? 'font-mono tabular-nums' }}" @if (! empty($style)) style="{{ $style }}" @endif
      @if ($ticking)
          x-data="{ base: {{ $seconds }}, t0: Date.now(), now: Date.now() }"
          x-init="setInterval(() => now = Date.now(), 1000)"
          x-text="@js($prefix) + ((s) => {{ $minutesOnly ? "`\${Math.floor(s / 60)}'`" : "Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0')" }})(base + Math.floor((now - t0) / 1000))"
          @if (($limit ?? null) !== null && $seconds < $limit)
              x-bind:style="base + Math.floor((now - t0) / 1000) >= {{ $limit }} ? @js($limitStyle) : @js($style ?? '')"
          @endif
      @endif
>{{ $prefix }}{{ $minutesOnly ? intdiv($seconds, 60)."'" : intdiv($seconds, 60).':'.sprintf('%02d', $seconds % 60) }}</span>
