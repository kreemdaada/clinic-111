<?php

return [

    'headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', env('APP_ENV') === 'production'),

        'hsts' => [
            'enabled' => env('SECURITY_HSTS_ENABLED', env('APP_ENV') === 'production'),
            'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
            'include_subdomains' => env('SECURITY_HSTS_SUBDOMAINS', true),
        ],

        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'SAMEORIGIN'),

        'content_type_options' => env('SECURITY_CONTENT_TYPE_OPTIONS', 'nosniff'),

        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

        'csp' => env('SECURITY_CSP', "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"),
    ],

];
