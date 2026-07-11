<?php

namespace App\Http\Requests\Nurses;

use App\Http\Requests\Concerns\ValidatesClinicScopedCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreNurseRequest extends FormRequest
{
    use ValidatesClinicScopedCode;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }

        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\-]+$/', $this->uniqueCodeWithinClinic('nurses')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => __('validation.custom.nurse.code_unique'),
            'code.regex' => __('validation.custom.nurse.code_regex'),
        ];
    }
}
