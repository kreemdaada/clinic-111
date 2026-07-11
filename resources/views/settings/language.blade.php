@extends('layouts.app')

@section('title', __('settings.title'))

@section('content')
<h1 class="page-title">{{ __('settings.language.title') }}</h1>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-error">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="card">
    <form method="POST" action="{{ route('settings.language.update') }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label class="form-label" for="locale">{{ __('settings.language.label') }}</label>
            <select class="form-input" id="locale" name="locale" required>
                @foreach (config('locales.supported', ['en']) as $code)
                    <option value="{{ $code }}" @selected(old('locale', auth()->user()->locale ?? 'en') === $code)>
                        {{ __('settings.language.options.'.$code) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:1rem;">
            <button type="submit" class="btn btn-primary">{{ __('common.actions.save') }}</button>
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('imports.index') }}" class="btn btn-secondary">{{ __('common.actions.back') }}</a>
        </div>
    </form>
</div>
@endsection
