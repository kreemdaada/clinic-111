<?php

return [
    'usd_exchange_rate' => env('ACCOUNTING_USD_EXCHANGE_RATE', '3.65'),
    'rub_to_aed_rate' => env('ACCOUNTING_RUB_TO_AED_RATE', '0.0481'),

    'upload' => [
        'disk' => env('ACCOUNTING_UPLOAD_DISK', 'local'),
        'directory' => 'daily-reports',
        'max_kilobytes' => 10240,
        'allowed_extensions' => ['xlsx', 'xlsm'],
        'delete_after_import' => env('ACCOUNTING_DELETE_UPLOAD_AFTER_IMPORT', true),
    ],

    'patient_reference_hmac_key' => env('ACCOUNTING_PATIENT_REFERENCE_HMAC_KEY'),
];
