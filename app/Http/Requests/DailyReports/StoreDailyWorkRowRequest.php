<?php

namespace App\Http\Requests\DailyReports;

use App\Http\Requests\Concerns\ValidatesNurseCommissionWorkRow;
use App\Models\DailyReport;
use App\Models\Doctor;
use App\Rules\BelongsToCurrentClinic;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreDailyWorkRowRequest extends FormRequest
{
    use ValidatesNurseCommissionWorkRow;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var DailyReport|null $dailyReport */
        $dailyReport = $this->route('dailyReport');

        return [
            'doctor_id' => ['required', 'integer', new BelongsToCurrentClinic(Doctor::class)],
            'day' => ['required', 'integer', 'min:1', 'max:31'],
            'work_row_id' => [
                'nullable',
                'integer',
                Rule::exists('daily_work_rows', 'id')->where(
                    'daily_report_id',
                    $dailyReport?->id ?? 0,
                ),
            ],
            'dhs_amount' => ['nullable', 'numeric'],
            'cheque_amount' => ['nullable', 'numeric', 'min:0'],
            'tabby_amount' => ['nullable', 'numeric', 'min:0'],
            'usd_amount' => ['nullable', 'numeric', 'min:0'],
            'visa_amount' => ['nullable', 'numeric', 'min:0'],
            'treatment_lines' => ['required', 'array', 'min:1'],
            'treatment_lines.*.code' => ['required', 'string', 'max:32'],
            'treatment_lines.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'treatment_lines.*.nurse_id' => ['nullable', 'integer'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
