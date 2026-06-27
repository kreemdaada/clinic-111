<?php

return [
    'legacy_clinic_code' => env('ACCOUNTING_LEGACY_CLINIC_CODE', 'CLINIC_111'),

    'usd_exchange_rate' => env('ACCOUNTING_USD_EXCHANGE_RATE', '3.65'),
    'rub_to_aed_rate' => env('ACCOUNTING_RUB_TO_AED_RATE', '0.0481'),

    /*
     * 1 unit of foreign currency → AED (internal storage pivot).
     * USD uses usd_exchange_rate above; add env overrides per currency as needed.
     */
    'currency_to_aed_rates' => [
        'EUR' => env('ACCOUNTING_EUR_TO_AED_RATE', '3.97'),
        'GBP' => env('ACCOUNTING_GBP_TO_AED_RATE', '4.65'),
        'SAR' => env('ACCOUNTING_SAR_TO_AED_RATE', '0.97'),
        'QAR' => env('ACCOUNTING_QAR_TO_AED_RATE', '1.00'),
        'OMR' => env('ACCOUNTING_OMR_TO_AED_RATE', '9.48'),
        'KWD' => env('ACCOUNTING_KWD_TO_AED_RATE', '11.90'),
        'BHD' => env('ACCOUNTING_BHD_TO_AED_RATE', '9.68'),
        'INR' => env('ACCOUNTING_INR_TO_AED_RATE', '0.044'),
        'PKR' => env('ACCOUNTING_PKR_TO_AED_RATE', '0.013'),
        'EGP' => env('ACCOUNTING_EGP_TO_AED_RATE', '0.075'),
    ],

    'upload' => [
        'disk' => env('ACCOUNTING_UPLOAD_DISK', 'local'),
        'directory' => 'daily-reports',
        'max_kilobytes' => 10240,
        'allowed_extensions' => ['xlsx', 'xlsm'],
        'delete_after_import' => env('ACCOUNTING_DELETE_UPLOAD_AFTER_IMPORT', true),
    ],

    'patient_reference_hmac_key' => env('ACCOUNTING_PATIENT_REFERENCE_HMAC_KEY'),
];
