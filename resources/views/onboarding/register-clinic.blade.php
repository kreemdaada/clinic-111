@extends('layouts.app')

@section('title', __('onboarding.register.title'))

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
        <h1>{{ __('onboarding.register.heading') }}</h1>
        <p>{{ __('onboarding.register.lead') }}</p>

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

            <div class="section-title">{{ __('onboarding.register.sections.clinic_information') }}</div>
            <div class="form-grid">
                <div class="form-group span-2">
                    <label class="form-label" for="clinic_name">{{ __('onboarding.register.labels.clinic_name') }}</label>
                    <input class="form-input" type="text" id="clinic_name" name="clinic_name" value="{{ old('clinic_name') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="clinic_code">{{ __('onboarding.register.labels.clinic_code') }}</label>
                    <input class="form-input" type="text" id="clinic_code" name="clinic_code" value="{{ old('clinic_code') }}" required placeholder="{{ __('onboarding.register.placeholders.clinic_code') }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="country">{{ __('onboarding.register.labels.country') }}</label>
                    <input class="form-input" type="text" id="country" name="country" value="{{ old('country') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="currency">{{ __('onboarding.register.labels.currency') }}</label>
                    <select class="form-input" id="currency" name="currency" required>
                        @foreach ($currencies as $code => $label)
                            <option value="{{ $code }}" @selected(old('currency') === $code)>
                                {{ $code }} — {{ __('onboarding.currencies.'.$code) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="timezone">{{ __('onboarding.register.labels.timezone') }}</label>
                    <select class="form-input" id="timezone" name="timezone" required>
                        @foreach ($timezones as $identifier => $label)
                            <option value="{{ $identifier }}" @selected(old('timezone', 'Asia/Dubai') === $identifier)>
                                {{ __('onboarding.timezones.'.str_replace('/', '_', $identifier)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="section-title">{{ __('onboarding.register.sections.owner_admin') }}</div>
            <div class="form-grid">
                <div class="form-group span-2">
                    <label class="form-label" for="owner_name">{{ __('onboarding.register.labels.owner_name') }}</label>
                    <input class="form-input" type="text" id="owner_name" name="owner_name" value="{{ old('owner_name') }}" required>
                </div>
                <div class="form-group span-2">
                    <label class="form-label" for="owner_email">{{ __('onboarding.register.labels.email') }}</label>
                    <input class="form-input" type="email" id="owner_email" name="owner_email" value="{{ old('owner_email') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="owner_password">{{ __('onboarding.register.labels.password') }}</label>
                    <input class="form-input" type="password" id="owner_password" name="owner_password" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="owner_password_confirmation">{{ __('onboarding.register.labels.confirm_password') }}</label>
                    <input class="form-input" type="password" id="owner_password_confirmation" name="owner_password_confirmation" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:1rem;">{{ __('auth.create_clinic') }}</button>
        </form>

        <p style="margin-top:1rem;font-size:0.85rem;color:#64748b;text-align:center;">
            {{ __('auth.already_have_account') }} <a href="{{ route('login') }}">{{ __('auth.sign_in') }}</a>
        </p>
    </div>
</div>
@endsection
