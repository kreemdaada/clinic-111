<?php

namespace App\Http\Requests\Doctors;

use App\Enums\CommissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoctorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'commission_type' => ['required', Rule::enum(CommissionType::class)],
            'commission_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'required_if:commission_type,percentage',
            ],
            'default_lab_id' => ['nullable', 'integer', 'exists:labs,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
