<?php

namespace App\Http\Requests\Clinics;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\-]+$/', 'unique:clinics,code'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'timezone' => ['required', 'string', 'max:64', 'timezone:all'],
            'country' => ['required', 'string', 'max:120'],
        ];
    }
}
