<?php

namespace App\Http\Requests\LabPrices;

use App\Models\LabPrice;
use App\Support\LabPriceOverlapValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLabPriceRequest extends FormRequest
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
            'lab_id' => ['sometimes', 'required', 'integer', 'exists:labs,id'],
            'treatment_id' => ['sometimes', 'required', 'integer', 'exists:treatments,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'unit_cost' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var LabPrice $labPrice */
            $labPrice = $this->route('labPrice');
            $data = $validator->getData();

            $labId = (int) ($data['lab_id'] ?? $labPrice->lab_id);
            $treatmentId = (int) ($data['treatment_id'] ?? $labPrice->treatment_id);
            $doctorId = array_key_exists('doctor_id', $data)
                ? ($data['doctor_id'] !== null && $data['doctor_id'] !== '' ? (int) $data['doctor_id'] : null)
                : $labPrice->doctor_id;
            $validFrom = array_key_exists('valid_from', $data) ? $data['valid_from'] : $labPrice->valid_from?->toDateString();
            $validTo = array_key_exists('valid_to', $data) ? $data['valid_to'] : $labPrice->valid_to?->toDateString();
            $willBeActive = array_key_exists('is_active', $data) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : $labPrice->is_active;

            if (! $willBeActive) {
                return;
            }

            if (app(LabPriceOverlapValidator::class)->hasActiveOverlap(
                $labId,
                $treatmentId,
                $doctorId,
                $validFrom,
                $validTo,
                $labPrice->id,
            )) {
                $validator->errors()->add(
                    'lab_id',
                    'An active price already exists for this lab, treatment, doctor override, and validity period.',
                );
            }
        });
    }
}
