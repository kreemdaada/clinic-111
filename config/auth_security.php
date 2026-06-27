<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login Throttle
    |--------------------------------------------------------------------------
    |
    | Failed login attempts are tracked per email + IP using Laravel RateLimiter.
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

];
