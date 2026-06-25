<?php

namespace App\Http\Requests\LabPrices;

use Illuminate\Foundation\Http\FormRequest;

class ListLabPricesRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'lab_id' => ['nullable', 'integer', 'exists:labs,id'],
            'treatment_id' => ['nullable', 'integer', 'exists:treatments,id'],
            'doctor_id' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
