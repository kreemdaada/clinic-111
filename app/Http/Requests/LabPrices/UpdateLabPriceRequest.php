<?php

namespace App\Http\Requests\LabPrices;

use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Rules\BelongsToCurrentClinic;
use App\Rules\SupportedCurrency;
use App\Support\LabPriceOverlapValidator;
use App\Services\Configuration\CurrentClinicResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLabPriceRequest extends FormRequest
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

        if ($this->has('is_active')) {
            $this->merge(['is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lab_id' => ['sometimes', 'required', 'integer', new BelongsToCurrentClinic(Lab::class)],
            'treatment_id' => ['sometimes', 'required', 'integer', new BelongsToCurrentClinic(Treatment::class)],
            'doctor_id' => ['nullable', 'integer', new BelongsToCurrentClinic(Doctor::class, nullable: true)],
            'unit_cost' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', new SupportedCurrency],
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
                app(CurrentClinicResolver::class)->resolveId(),
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
