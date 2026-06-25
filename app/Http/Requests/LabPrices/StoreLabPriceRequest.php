<?php

namespace App\Http\Requests\LabPrices;

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
            'lab_id' => ['required', 'integer', 'exists:labs,id'],
            'treatment_id' => ['required', 'integer', 'exists:treatments,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'unit_cost' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
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
