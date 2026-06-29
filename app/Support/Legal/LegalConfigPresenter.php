<?php

namespace App\Support\Legal;

class LegalConfigPresenter
{
    /** @var array<string, string> */
    private const KEY_TO_ENV = [
        'operator_name' => 'LEGAL_OPERATOR_NAME',
        'business_name' => 'LEGAL_BUSINESS_NAME',
        'street' => 'LEGAL_ADDRESS_STREET',
        'postal_code' => 'LEGAL_ADDRESS_POSTAL_CODE',
        'city' => 'LEGAL_ADDRESS_CITY',
        'country' => 'LEGAL_ADDRESS_COUNTRY',
        'email' => 'LEGAL_EMAIL',
        'phone' => 'LEGAL_PHONE',
        'privacy_email' => 'LEGAL_PRIVACY_EMAIL',
        'hosting_provider_name' => 'LEGAL_HOSTING_PROVIDER_NAME',
    ];

    public function isProduction(): bool
    {
        return app()->environment('production');
    }

    public function has(string $key): bool
    {
        $value = config("legal.{$key}");

        return is_string($value) && trim($value) !== '';
    }

    /**
     * Display a configured value, or a dev/test placeholder, or null in production.
     */
    public function display(string $key): ?string
    {
        $value = config("legal.{$key}");

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if ($this->isProduction()) {
            return null;
        }

        $envKey = self::KEY_TO_ENV[$key] ?? 'LEGAL_'.strtoupper($key);

        return "[Platzhalter: {$envKey}]";
    }

    public function hasCompleteAddress(): bool
    {
        return $this->has('street')
            && $this->has('postal_code')
            && $this->has('city');
    }

    public function hasRequiredImprintFields(): bool
    {
        return $this->has('operator_name')
            && $this->hasCompleteAddress()
            && $this->has('email');
    }

    public function contactEmail(): ?string
    {
        if ($this->has('email')) {
            return trim((string) config('legal.email'));
        }

        return $this->display('email');
    }

    public function contactMailtoUrl(): ?string
    {
        if (! $this->has('email')) {
            return null;
        }

        return 'mailto:'.trim((string) config('legal.email'));
    }

    public function privacyContactEmail(): ?string
    {
        if ($this->has('privacy_email')) {
            return trim((string) config('legal.privacy_email'));
        }

        return $this->contactEmail();
    }

    public function privacyLastUpdated(): string
    {
        return trim((string) config('legal.privacy_last_updated', 'Juni 2026'));
    }

    public function businessName(): string
    {
        return trim((string) config('legal.business_name', 'DentalFinance'));
    }
}
