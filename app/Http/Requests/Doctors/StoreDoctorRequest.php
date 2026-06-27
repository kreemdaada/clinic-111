<?php

namespace App\Http\Requests\Doctors;

use App\Enums\CommissionType;
use App\Http\Requests\Concerns\ValidatesClinicScopedCode;
use App\Models\Lab;
use App\Rules\BelongsToCurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDoctorRequest extends FormRequest
{
    use ValidatesClinicScopedCode;
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
            'code' => ['required', 'string', 'max:32', $this->uniqueCodeWithinClinic('doctors')],
            'commission_type' => ['required', Rule::enum(CommissionType::class)],
            'commission_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'required_if:commission_type,percentage',
            ],
            'default_lab_id' => ['nullable', 'integer', new BelongsToCurrentClinic(Lab::class, nullable: true)],
            'seed_full_lab_billing' => ['sometimes', 'boolean'],
        ];
    }
}
