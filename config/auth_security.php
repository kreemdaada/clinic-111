<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login Throttle
    |--------------------------------------------------------------------------
    |
    | Failed login attempts are tracked per email + IP + user-agent (ADR-033).
    | After max_attempts failures, login is blocked for decay_seconds.
    |
    */

    'login' => [
        'max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('AUTH_LOGIN_DECAY_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Clinic Registration Throttle
    |--------------------------------------------------------------------------
    |
    | Public onboarding POST /register-clinic is limited per IP address.
    |
    */

    'registration' => [
        'max_attempts' => (int) env('AUTH_REGISTRATION_MAX_ATTEMPTS', 3),
        'decay_seconds' => (int) env('AUTH_REGISTRATION_DECAY_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Forgot Password Throttle
    |--------------------------------------------------------------------------
    |
    | Public forgot/reset password POSTs are limited per email + IP (ADR-040).
    |
    */

    'password_reset' => [
        'max_attempts' => (int) env('AUTH_PASSWORD_RESET_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('AUTH_PASSWORD_RESET_DECAY_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration CAPTCHA
    |--------------------------------------------------------------------------
    |
    | Contract-based CAPTCHA verification for public clinic registration.
    | Disabled by default in local/testing; bind a real driver in production.
    |
    */

    'captcha' => [
        'enabled' => env('AUTH_CAPTCHA_ENABLED', false),
        'driver' => env('AUTH_CAPTCHA_DRIVER', 'fake'),
        'fake_token' => env('AUTH_CAPTCHA_FAKE_TOKEN', 'test-captcha-token'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Verification (local development)
    |--------------------------------------------------------------------------
    |
    | When MAIL_MAILER is log or array, verification emails are not delivered.
    | In local, auto-verify new owners by default and show a signed link on the
    | verify page for accounts that are still unverified. Override with env vars.
    |
    */

    'email_verification' => [
        'auto_verify_without_delivery' => env('AUTH_EMAIL_VERIFICATION_AUTO_VERIFY'),
        'show_link_without_delivery' => env('AUTH_EMAIL_VERIFICATION_SHOW_LINK'),
    ],

];
