<?php

namespace App\Http\Requests\Labs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListLabsRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in(['all', 'active', 'inactive'])],
        ];
    }
}
