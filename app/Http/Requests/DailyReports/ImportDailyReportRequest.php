<?php

namespace App\Http\Requests\DailyReports;

use Illuminate\Foundation\Http\FormRequest;

class ImportDailyReportRequest extends FormRequest
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.uploaded' => 'Upload failed before PHP received the file. Stop any running server and restart with: ./bin/serve (not php artisan serve). Current PHP limit: upload_max_filesize='.ini_get('upload_max_filesize').', post_max_size='.ini_get('post_max_size').'.',
            'file.max' => 'The file is too large. Maximum allowed size is '.((int) config('accounting.upload.max_kilobytes') / 1024).' MB.',
        ];
    }
}
