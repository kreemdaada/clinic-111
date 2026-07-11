<?php

return [
    'register' => [
        'title' => 'Register Clinic',
        'heading' => 'Register Your Clinic',
        'lead' => 'Create your DentalFinance account and configure your business rules after onboarding.',
        'sections' => [
            'clinic_information' => 'Clinic Information',
            'owner_admin' => 'Owner / Admin',
        ],
        'labels' => [
            'clinic_name' => 'Clinic Name',
            'clinic_code' => 'Clinic Code',
            'country' => 'Country',
            'currency' => 'Currency',
            'timezone' => 'Timezone',
            'owner_name' => 'Your Name',
            'email' => 'Email',
            'password' => 'Password',
            'confirm_password' => 'Confirm Password',
        ],
        'placeholders' => [
            'clinic_code' => 'MY_CLINIC',
        ],
    ],
    'currencies' => [
        'AED' => 'UAE Dirham',
        'EUR' => 'Euro',
        'USD' => 'US Dollar',
        'SAR' => 'Saudi Riyal',
        'GBP' => 'British Pound',
    ],
    'timezones' => [
        'Asia_Dubai' => 'Asia/Dubai (UAE)',
        'Asia_Riyadh' => 'Asia/Riyadh (Saudi Arabia)',
        'Asia_Kuwait' => 'Asia/Kuwait',
        'Asia_Qatar' => 'Asia/Qatar',
        'Asia_Muscat' => 'Asia/Muscat (Oman)',
        'Asia_Bahrain' => 'Asia/Bahrain',
        'Asia_Karachi' => 'Asia/Karachi (Pakistan)',
        'Asia_Kolkata' => 'Asia/Kolkata (India)',
        'Africa_Cairo' => 'Africa/Cairo (Egypt)',
        'Europe_London' => 'Europe/London (UK)',
        'Europe_Berlin' => 'Europe/Berlin (Central Europe)',
        'Europe_Paris' => 'Europe/Paris',
        'America_New_York' => 'America/New_York (US Eastern)',
        'America_Chicago' => 'America/Chicago (US Central)',
        'America_Denver' => 'America/Denver (US Mountain)',
        'America_Los_Angeles' => 'America/Los_Angeles (US Pacific)',
        'UTC' => 'UTC',
    ],
];
