<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates login credentials for web session and API token endpoints.
 *
 * Used by {@see \App\Http\Controllers\Web\AuthController} and {@see \App\Http\Controllers\Api\AuthController}.
 */
class LoginRequest extends FormRequest
{
    /**
     * Login is allowed for guests — no prior authorization required.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for email + password login.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
