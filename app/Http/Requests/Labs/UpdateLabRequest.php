<?php

namespace App\Http\Requests\Labs;

use App\Models\Lab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabRequest extends FormRequest
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
        /** @var Lab $lab */
        $lab = $this->route('lab');

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_\-]+$/',
                Rule::unique('labs', 'code')->ignore($lab->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
