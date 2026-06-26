<?php

namespace App\Http\Requests\DoctorFixedFees;

use Illuminate\Foundation\Http\FormRequest;

class ListDoctorFixedFeesRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:120'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'treatment_id' => ['nullable', 'integer', 'exists:treatments,id'],
            'status' => ['nullable', 'string', 'in:all,active,inactive'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
