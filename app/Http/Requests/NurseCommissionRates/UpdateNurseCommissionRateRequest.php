<?php

namespace App\Http\Requests\NurseCommissionRates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNurseCommissionRateRequest extends FormRequest
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
            'nurse_id' => ['sometimes', 'integer'],
            'commission_percentage' => ['sometimes', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
