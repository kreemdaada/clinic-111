@extends('layouts.app')

@section('title', __('auth.sign_in'))

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
        <h1>Sign in to DentalFinance</h1>
        <p class="login-lead">Manage daily reports, imports, and clinic configuration.</p>

        @if ($errors->any())
            <div class="alert alert-error">
                <ul class="error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input class="form-input" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input class="form-input" type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">{{ __('auth.sign_in') }}</button>
        </form>

        <p class="login-footer">
            <a href="{{ route('register-clinic.create') }}">{{ __('auth.register_clinic') }}</a>
            ·
            <a href="{{ route('landing') }}">{{ __('auth.back_to_homepage') }}</a>
        </p>
    </div>
</div>
@endsection
