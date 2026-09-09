@extends('layouts.app')

@section('title', __('auth.reset_password_title'))

@push('styles')
<style>
    .login-wrap {
        min-height: calc(100vh - 56px - 3rem);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-card {
        width: 100%;
        max-width: 400px;
    }

    .login-card h1 {
        font-size: 1.35rem;
        margin-bottom: 0.25rem;
        color: var(--text);
    }

    .login-card .login-lead {
        color: var(--text-muted);
        margin-bottom: 1.25rem;
        font-size: 0.9rem;
    }

    .login-footer {
        margin-top: 1rem;
        font-size: 0.85rem;
        color: var(--text-muted);
        text-align: center;
    }

    .error-list {
        list-style: none;
        margin: 0;
    }
</style>
@endpush

@section('content')
<div class="login-wrap">
    <div class="card login-card">
        <h1>{{ __('auth.reset_password_title') }}</h1>
        <p class="login-lead">{{ __('auth.reset_password_lead') }}</p>

        @if ($errors->any())
            <div class="alert alert-error">
                <ul class="error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label class="form-label" for="email">{{ __('auth.email') }}</label>
                <input class="form-input" type="email" id="email" name="email" value="{{ old('email', $email) }}" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">{{ __('auth.password') }}</label>
                <input class="form-input" type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="password_confirmation">{{ __('auth.password_confirmation') }}</label>
                <input class="form-input" type="password" id="password_confirmation" name="password_confirmation" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">{{ __('auth.reset_password') }}</button>
        </form>

        <p class="login-footer">
            <a href="{{ route('login') }}">{{ __('auth.back_to_sign_in') }}</a>
        </p>
    </div>
</div>
@endsection
