@extends('layouts.app')

@section('title', 'Verify Email')

@section('content')
<div class="card" style="max-width:520px;margin:2rem auto;padding:1.5rem;">
    <h1 class="page-title">Verify your email</h1>
    <p class="page-subtitle">
        We sent a verification link to <strong>{{ auth()->user()->email }}</strong>.
        Please verify your email before using the clinic dashboard.
    </p>

    @if (session('status') === 'verification-link-sent')
        <div class="alert alert-success">A new verification link has been sent.</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary">Resend verification email</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" style="margin-top:1rem;">
        @csrf
        <button type="submit" class="btn btn-ghost">Sign out</button>
    </form>
</div>
@endsection
