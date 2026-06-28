<?php

namespace App\Http\Requests\DoctorFixedFees;

use App\Enums\CommissionType;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Rules\BelongsToCurrentClinic;
use App\Rules\SupportedCurrency;
use App\Support\DoctorFixedFeeOverlapValidator;
use App\Services\Configuration\CurrentClinicResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDoctorFixedFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'doctor_id' => [
                'required',
                'integer',
                new BelongsToCurrentClinic(Doctor::class),
                Rule::exists('doctors', 'id')->where(fn ($query) => $query
                    ->where('commission_type', CommissionType::Fixed->value)
                    ->where('clinic_id', $this->user()?->clinic_id ?? 0)),
            ],
            'treatment_id' => ['required', 'integer', new BelongsToCurrentClinic(Treatment::class)],
            'fee_amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3', new SupportedCurrency],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = $validator->getData();

            if (app(DoctorFixedFeeOverlapValidator::class)->hasActiveOverlap(
                app(CurrentClinicResolver::class)->resolveId(),
                (int) $data['doctor_id'],
                (int) $data['treatment_id'],
                $data['valid_from'] ?? null,
                $data['valid_to'] ?? null,
            )) {
                $validator->errors()->add(
                    'doctor_id',
                    'An active fee rule already exists for this doctor, treatment, and validity period.',
                );
            }
        });
    }
}
