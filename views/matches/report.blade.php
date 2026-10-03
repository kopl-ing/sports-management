@extends('kopling-sports-management::layouts.sports-management', ['sidebar' => false, 'label' => false])

@section('content')
    {{-- Same history handling as track.blade.php. --}}
    <div hx-replace-url:inherited="true" class="max-w-3xl flex flex-col gap-8">
        @if ($errors->any())
            <p role="alert" class="alert alert-error py-2 text-sm">{{ $errors->first() }}</p>
        @endif
        <a href="{{ route('kopling-sports-management::sports-management/matches.show', [$team, $match]) }}" class="link link-hover text-sm opacity-60 -mb-6">
            {{ __('kopling-sports-management::messages.back_to_match') }}
        </a>
        @include('kopling-sports-management::matches.tracking.report')
        @include('kopling-sports-management::matches.tracking.undo')
    </div>
@endsection
