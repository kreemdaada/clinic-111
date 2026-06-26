<?php

namespace App\Http\Requests\Onboarding;

use App\Support\ClinicRegistrationOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'clinic_name' => ['required', 'string', 'max:120'],
            'clinic_code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\-]+$/', 'unique:clinics,code'],
            'country' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3', Rule::in(ClinicRegistrationOptions::currencyCodes())],
            'timezone' => ['required', 'string', 'max:64', Rule::in(ClinicRegistrationOptions::timezoneIdentifiers())],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $clinicCode = $this->input('clinic_code');
        $currency = $this->input('currency');
        $ownerEmail = $this->input('owner_email');

        $normalized = [];

        if (is_string($clinicCode)) {
            $normalized['clinic_code'] = strtoupper(trim($clinicCode));
        }

        if (is_string($currency)) {
            $normalized['currency'] = strtoupper(trim($currency));
        }

        if (is_string($ownerEmail)) {
            $normalized['owner_email'] = strtolower(trim($ownerEmail));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
