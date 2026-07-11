@extends('layouts.app')

@section('title', 'Verify Email')

@section('content')
<div class="card" style="max-width:520px;margin:2rem auto;padding:1.5rem;">
    <h1 class="page-title">Verify your email</h1>
    <p class="page-subtitle">
        We sent a verification link to <strong>{{ auth()->user()->email }}</strong>.
        Please verify your email before using the clinic dashboard.
    </p>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($verificationUrl ?? null)
        <div class="alert alert-info" data-testid="verification-link-dev">
            <strong>Local development:</strong> Mail is not delivered (<code>{{ config('mail.default') }}</code> driver).
            Use this link to verify without a real inbox:
            <p style="margin:0.75rem 0 0;word-break:break-all;">
                <a href="{{ $verificationUrl }}">Verify email now</a>
            </p>
        </div>
    @endif

    @if (session('status') === 'verification-link-sent')
        <div class="alert alert-success">A new verification link has been sent.</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary">{{ __('auth.resend_verification') }}</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" style="margin-top:1rem;">
        @csrf
        <button type="submit" class="btn btn-ghost">{{ __('navigation.user.logout') }}</button>
    </form>
</div>
@endsection
