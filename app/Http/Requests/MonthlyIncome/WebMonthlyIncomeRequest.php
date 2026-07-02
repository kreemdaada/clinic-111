<?php

namespace App\Http\Requests\MonthlyIncome;

use Illuminate\Foundation\Http\FormRequest;

class WebMonthlyIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }
}
