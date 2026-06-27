<?php

namespace App\Http\Requests\DailyReports;

use App\Services\Configuration\BusinessConfigurationService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates daily Excel upload for web and API import endpoints.
 *
 * Month is inferred from the file name — `report_date` is not submitted by the client.
 */
class ImportDailyReportRequest extends FormRequest
{
    /**
     * Import is authorized at route level via role middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Require an Excel macro-enabled or standard workbook within configured size limit.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) config('accounting.upload.max_kilobytes');

        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xlsm',
                'max:'.$maxKilobytes,
            ],
        ];
    }

    /**
     * User-friendly messages for common PHP upload limit failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.uploaded' => 'Upload failed before PHP received the file. Stop any running server and restart with: ./bin/serve (not php artisan serve). Current PHP limit: upload_max_filesize='.ini_get('upload_max_filesize').', post_max_size='.ini_get('post_max_size').'.',
            'file.max' => 'The file is too large. Maximum allowed size is '.((int) config('accounting.upload.max_kilobytes') / 1024).' MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! app(BusinessConfigurationService::class)->canImport()) {
                $validator->errors()->add('file', BusinessConfigurationService::INCOMPLETE_MESSAGE);
            }
        });
    }
}
