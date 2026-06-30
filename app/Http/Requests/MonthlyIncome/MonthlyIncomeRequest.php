<?php

namespace App\Http\Requests\MonthlyIncome;

use App\Http\Controllers\Api\MonthlyIncomeController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the `month` query parameter for monthly income API requests.
 *
 * Used by {@see MonthlyIncomeController::index()}.
 */
class MonthlyIncomeRequest extends FormRequest
{
    /**
     * Monthly income is authorized at route level via role middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Require calendar month in `YYYY-MM` format (e.g. `2026-01`).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
        ];
    }
}
