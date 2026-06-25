<?php

namespace App\Http\Requests\LabPrices;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabPriceRequest extends FormRequest
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
            'lab_id' => ['required', 'integer', 'exists:labs,id'],
            'treatment_id' => ['required', 'integer', 'exists:treatments,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }
}
