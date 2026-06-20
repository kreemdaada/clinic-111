@extends('layouts.app')

@section('title', 'Login — Clinic 111')

@push('styles')
<style>
    .login-wrap {
        min-height: calc(100vh - 3rem);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .login-card { width: 100%; max-width: 400px; }
    .login-card h1 { font-size: 1.35rem; margin-bottom: 0.25rem; }
    .login-card p { color: #64748b; margin-bottom: 1.25rem; font-size: 0.9rem; }
    .error-list { color: #991b1b; font-size: 0.85rem; margin-bottom: 1rem; }
</style>
@endpush

@section('content')
<div class="login-wrap">
    <div class="card login-card">
        <h1>Clinic 111 Accounting</h1>
        <p>Sign in to import daily Excel reports.</p>

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
            <button type="submit" class="btn btn-primary" style="width:100%;">Sign in</button>
        </form>

        <p style="margin-top:1rem;font-size:0.8rem;color:#94a3b8;">
            Demo: accountant@clinic.test / password
        </p>
    </div>
</div>
@endsection
