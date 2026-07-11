@extends('layouts.app')

@section('title', 'Register Clinic')

@push('styles')
<style>
    .onboarding-wrap {
        min-height: calc(100vh - 3rem);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem 0;
    }
    .onboarding-card { width: 100%; max-width: 640px; }
    .onboarding-card h1 { font-size: 1.35rem; margin-bottom: 0.25rem; color: var(--text); }
    .onboarding-card p { color: var(--text-muted); margin-bottom: 1.25rem; font-size: 0.9rem; }
    .section-title {
        font-size: 0.95rem;
        font-weight: 600;
        margin: 1.25rem 0 0.75rem;
        color: var(--text);
    }
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem 1rem;
    }
    .form-grid .span-2 { grid-column: span 2; }
    @media (max-width: 640px) {
        .form-grid { grid-template-columns: 1fr; }
        .form-grid .span-2 { grid-column: span 1; }
    }
    .error-list { color: #991b1b; font-size: 0.85rem; margin-bottom: 1rem; }
</style>
@endpush

@section('content')
<div class="onboarding-wrap">
    <div class="card onboarding-card">
        <h1>Register Your Clinic</h1>
        <p>Create your DentalFinance account and configure your business rules after onboarding.</p>

        @if ($errors->any())
            <div class="alert alert-error">
                <ul class="error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register-clinic.store') }}">
            @csrf

            <div class="section-title">Clinic Information</div>
            <div class="form-grid">
                <div class="form-group span-2">
                    <label class="form-label" for="clinic_name">Clinic Name</label>
                    <input class="form-input" type="text" id="clinic_name" name="clinic_name" value="{{ old('clinic_name') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="clinic_code">Clinic Code</label>
                    <input class="form-input" type="text" id="clinic_code" name="clinic_code" value="{{ old('clinic_code') }}" required placeholder="MY_CLINIC">
                </div>
                <div class="form-group">
                    <label class="form-label" for="country">Country</label>
                    <input class="form-input" type="text" id="country" name="country" value="{{ old('country') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="currency">Currency</label>
                    <select class="form-input" id="currency" name="currency" required>
                        @foreach ($currencies as $code => $label)
                            <option value="{{ $code }}" @selected(old('currency') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="timezone">Timezone</label>
                    <select class="form-input" id="timezone" name="timezone" required>
                        @foreach ($timezones as $identifier => $label)
                            <option value="{{ $identifier }}" @selected(old('timezone', 'Asia/Dubai') === $identifier)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="section-title">Owner / Admin</div>
            <div class="form-grid">
                <div class="form-group span-2">
                    <label class="form-label" for="owner_name">Your Name</label>
                    <input class="form-input" type="text" id="owner_name" name="owner_name" value="{{ old('owner_name') }}" required>
                </div>
                <div class="form-group span-2">
                    <label class="form-label" for="owner_email">Email</label>
                    <input class="form-input" type="email" id="owner_email" name="owner_email" value="{{ old('owner_email') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="owner_password">Password</label>
                    <input class="form-input" type="password" id="owner_password" name="owner_password" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="owner_password_confirmation">Confirm Password</label>
                    <input class="form-input" type="password" id="owner_password_confirmation" name="owner_password_confirmation" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:1rem;">{{ __('auth.create_clinic') }}</button>
        </form>

        <p style="margin-top:1rem;font-size:0.85rem;color:#64748b;text-align:center;">
            Already have an account? <a href="{{ route('login') }}">{{ __('auth.sign_in') }}</a>
        </p>
    </div>
</div>
@endsection
