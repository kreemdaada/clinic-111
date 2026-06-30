<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ValidatesClinicScopedCode
{
    protected function uniqueCodeWithinClinic(string $table, ?int $ignoreId = null): Unique
    {
        $clinicId = $this->user()?->clinic_id;

        $rule = Rule::unique($table, 'code');

        if ($clinicId !== null) {
            $rule->where('clinic_id', $clinicId);
        }

        if ($ignoreId !== null) {
            $rule->ignore($ignoreId);
        }

        return $rule;
    }
}
