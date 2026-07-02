<?php

namespace App\Http\Requests\Treatments;

use App\Http\Requests\Concerns\ValidatesClinicScopedCode;
use App\Http\Requests\Concerns\ValidatesTreatmentPriceFields;
use App\Models\Treatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTreatmentRequest extends FormRequest
{
    use ValidatesClinicScopedCode;
    use ValidatesTreatmentPriceFields;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTreatmentPriceFieldsForValidation();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Treatment $treatment */
        $treatment = $this->route('treatment');

        return [
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_\-]+$/',
                Rule::unique('treatments', 'code')->ignore($treatment->id)->where('clinic_id', $treatment->clinic_id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'has_lab_cost' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->treatmentPriceFieldRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->treatmentPriceFieldMessages();
    }
}
