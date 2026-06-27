<?php

namespace App\Http\Requests\DoctorFixedFees;

use App\Models\Doctor;
use App\Models\Treatment;
use App\Rules\BelongsToCurrentClinic;
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
            'doctor_id' => ['nullable', 'integer', new BelongsToCurrentClinic(Doctor::class, nullable: true)],
            'treatment_id' => ['nullable', 'integer', new BelongsToCurrentClinic(Treatment::class, nullable: true)],
            'status' => ['nullable', 'string', 'in:all,active,inactive'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
