<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Operator / Imprint (§ 5 DDG)
    |--------------------------------------------------------------------------
    |
    | Set all required values before production deployment.
    | See docs/LEGAL_SETUP.md
    |
    */

    'operator_name' => env('LEGAL_OPERATOR_NAME'),
    'business_name' => env('LEGAL_BUSINESS_NAME', 'DentalFinance'),
    'street' => env('LEGAL_ADDRESS_STREET'),
    'postal_code' => env('LEGAL_ADDRESS_POSTAL_CODE'),
    'city' => env('LEGAL_ADDRESS_CITY'),
    'country' => env('LEGAL_ADDRESS_COUNTRY', 'Deutschland'),
    'email' => env('LEGAL_EMAIL'),
    'phone' => env('LEGAL_PHONE'),

    'vat_id' => env('LEGAL_VAT_ID'),
    'commercial_register' => env('LEGAL_COMMERCIAL_REGISTER'),
    'register_number' => env('LEGAL_REGISTER_NUMBER'),

    'privacy_email' => env('LEGAL_PRIVACY_EMAIL'),

    'supervisory_authority_name' => env('LEGAL_SUPERVISORY_AUTHORITY_NAME'),
    'supervisory_authority_url' => env('LEGAL_SUPERVISORY_AUTHORITY_URL'),

    /*
    | Optional: only if editorial content under § 18 MStV applies.
    */
    'editorial_responsible_name' => env('LEGAL_EDITORIAL_RESPONSIBLE_NAME'),

    /*
    | Hosting (privacy policy section — configure before production).
    */
    'hosting_provider_name' => env('LEGAL_HOSTING_PROVIDER_NAME'),

    'privacy_last_updated' => env('LEGAL_PRIVACY_LAST_UPDATED', 'Juni 2026'),

];
