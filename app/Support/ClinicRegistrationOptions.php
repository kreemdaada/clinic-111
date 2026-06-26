<?php

namespace App\Support;

/**
 * Allowed currency and timezone choices for clinic registration (ADR-030).
 */
class ClinicRegistrationOptions
{
    /**
     * @return array<string, string> ISO code => display label
     */
    public static function currencies(): array
    {
        return [
            'AED' => 'AED — UAE Dirham',
            'USD' => 'USD — US Dollar',
            'EUR' => 'EUR — Euro',
            'GBP' => 'GBP — British Pound',
            'SAR' => 'SAR — Saudi Riyal',
            'QAR' => 'QAR — Qatari Riyal',
            'OMR' => 'OMR — Omani Rial',
            'KWD' => 'KWD — Kuwaiti Dinar',
            'BHD' => 'BHD — Bahraini Dinar',
            'INR' => 'INR — Indian Rupee',
            'PKR' => 'PKR — Pakistani Rupee',
            'EGP' => 'EGP — Egyptian Pound',
        ];
    }

    /**
     * @return array<string, string> IANA identifier => display label
     */
    public static function timezones(): array
    {
        return [
            'Asia/Dubai' => 'Asia/Dubai (UAE)',
            'Asia/Riyadh' => 'Asia/Riyadh (Saudi Arabia)',
            'Asia/Kuwait' => 'Asia/Kuwait',
            'Asia/Qatar' => 'Asia/Qatar',
            'Asia/Muscat' => 'Asia/Muscat (Oman)',
            'Asia/Bahrain' => 'Asia/Bahrain',
            'Asia/Karachi' => 'Asia/Karachi (Pakistan)',
            'Asia/Kolkata' => 'Asia/Kolkata (India)',
            'Africa/Cairo' => 'Africa/Cairo (Egypt)',
            'Europe/London' => 'Europe/London (UK)',
            'Europe/Berlin' => 'Europe/Berlin (Central Europe)',
            'Europe/Paris' => 'Europe/Paris',
            'America/New_York' => 'America/New_York (US Eastern)',
            'America/Chicago' => 'America/Chicago (US Central)',
            'America/Denver' => 'America/Denver (US Mountain)',
            'America/Los_Angeles' => 'America/Los_Angeles (US Pacific)',
            'UTC' => 'UTC',
        ];
    }

    /**
     * @return list<string>
     */
    public static function currencyCodes(): array
    {
        return array_keys(self::currencies());
    }

    /**
     * @return list<string>
     */
    public static function timezoneIdentifiers(): array
    {
        return array_keys(self::timezones());
    }
}
