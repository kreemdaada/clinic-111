<?php

namespace App\Http\Requests\Treatments;

use App\Models\Treatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTreatmentRequest extends FormRequest
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
        /** @var Treatment $treatment */
        $treatment = $this->route('treatment');

        return [
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_\-]+$/',
                Rule::unique('treatments', 'code')->ignore($treatment->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'has_lab_cost' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
