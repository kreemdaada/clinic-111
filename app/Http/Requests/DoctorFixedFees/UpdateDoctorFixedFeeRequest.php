<?php

namespace App\Http\Requests\DoctorFixedFees;

use App\Enums\CommissionType;
use App\Models\DoctorFixedFee;
use App\Support\DoctorFixedFeeOverlapValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDoctorFixedFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
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
            'doctor_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('doctors', 'id')->where(fn ($query) => $query->where('commission_type', CommissionType::Fixed->value)),
            ],
            'treatment_id' => ['sometimes', 'required', 'integer', 'exists:treatments,id'],
            'fee_amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', Rule::in(['AED', 'USD'])],
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

            /** @var DoctorFixedFee $doctorFixedFee */
            $doctorFixedFee = $this->route('doctorFixedFee');
            $data = $validator->getData();

            $doctorId = (int) ($data['doctor_id'] ?? $doctorFixedFee->doctor_id);
            $treatmentId = (int) ($data['treatment_id'] ?? $doctorFixedFee->treatment_id);
            $validFrom = array_key_exists('valid_from', $data) ? $data['valid_from'] : $doctorFixedFee->valid_from?->toDateString();
            $validTo = array_key_exists('valid_to', $data) ? $data['valid_to'] : $doctorFixedFee->valid_to?->toDateString();
            $willBeActive = array_key_exists('is_active', $data) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : $doctorFixedFee->is_active;

            if (! $willBeActive) {
                return;
            }

            if (app(DoctorFixedFeeOverlapValidator::class)->hasActiveOverlap(
                $doctorId,
                $treatmentId,
                $validFrom,
                $validTo,
                $doctorFixedFee->id,
            )) {
                $validator->errors()->add(
                    'doctor_id',
                    'An active fee rule already exists for this doctor, treatment, and validity period.',
                );
            }
        });
    }
}
