<?php

namespace App\Http\Requests\NurseCommissionRates;

use App\Models\Nurse;
use App\Rules\BelongsToCurrentClinic;
use Illuminate\Foundation\Http\FormRequest;

class StoreNurseCommissionRateRequest extends FormRequest
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
            'nurse_id' => ['required', 'integer', new BelongsToCurrentClinic(Nurse::class)],
            'commission_percentage' => ['required', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
