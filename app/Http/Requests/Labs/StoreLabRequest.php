<?php

namespace App\Http\Requests\Labs;

use App\Http\Requests\Concerns\ValidatesClinicScopedCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreLabRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\-]+$/', $this->uniqueCodeWithinClinic('labs')],
        ];
    }
}
