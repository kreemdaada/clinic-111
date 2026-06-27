<?php

namespace App\Http\Requests\Onboarding;

use App\Services\Security\CaptchaVerificationService;
use App\Support\ClinicRegistrationOptions;
use App\Support\SecurePassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'owner_password' => ['required', 'string', 'confirmed', SecurePassword::rule()],
            'captcha_token' => ['nullable', 'string', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var CaptchaVerificationService $captcha */
            $captcha = app(CaptchaVerificationService::class);

            if (! $captcha->isEnabled()) {
                return;
            }

            if (! $captcha->verify($this->input('captcha_token'), $this)) {
                $validator->errors()->add('captcha_token', 'CAPTCHA verification failed.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $genericRegistrationFailure = 'Registration could not be completed. Please check your details and try again.';

        return [
            'clinic_code.unique' => $genericRegistrationFailure,
            'owner_email.unique' => $genericRegistrationFailure,
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
