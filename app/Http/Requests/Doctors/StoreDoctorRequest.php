<?php

namespace App\Http\Requests\Doctors;

use App\Enums\CommissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDoctorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:32', 'unique:doctors,code'],
            'commission_type' => ['required', Rule::enum(CommissionType::class)],
            'commission_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'required_if:commission_type,percentage',
            ],
            'default_lab_id' => ['nullable', 'integer', 'exists:labs,id'],
            'seed_full_lab_billing' => ['sometimes', 'boolean'],
        ];
    }
}
