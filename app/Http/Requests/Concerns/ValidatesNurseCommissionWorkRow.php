<?php

namespace App\Http\Requests\Concerns;

use App\Services\Accounting\NurseCommissionWorkRowValidator;
use Illuminate\Validation\Validator;

trait ValidatesNurseCommissionWorkRow
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lines = $validator->getData()['treatment_lines'] ?? [];

            if (! is_array($lines)) {
                return;
            }

            $errors = app(NurseCommissionWorkRowValidator::class)->validate($lines);

            foreach ($errors as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        });
    }
}
