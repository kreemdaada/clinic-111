<?php

namespace App\Http\Requests\LabPrices;

use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Rules\BelongsToCurrentClinic;
use App\Rules\SupportedCurrency;
use App\Services\Configuration\CurrentClinicResolver;
use App\Support\LabPriceOverlapValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLabPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('doctor_id') && $this->input('doctor_id') === '') {
            $this->merge(['doctor_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lab_id' => ['required', 'integer', new BelongsToCurrentClinic(Lab::class)],
            'treatment_id' => ['required', 'integer', new BelongsToCurrentClinic(Treatment::class)],
            'doctor_id' => ['nullable', 'integer', new BelongsToCurrentClinic(Doctor::class, nullable: true)],
            'unit_cost' => ['required', 'numeric', 'min:0.01'],
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
            $doctorId = isset($data['doctor_id']) && $data['doctor_id'] !== '' ? (int) $data['doctor_id'] : null;

            if (app(LabPriceOverlapValidator::class)->hasActiveOverlap(
                app(CurrentClinicResolver::class)->resolveId(),
                (int) $data['lab_id'],
                (int) $data['treatment_id'],
                $doctorId,
                $data['valid_from'] ?? null,
                $data['valid_to'] ?? null,
            )) {
                $validator->errors()->add(
                    'lab_id',
                    'An active price already exists for this lab, treatment, doctor override, and validity period.',
                );
            }
        });
    }
}
