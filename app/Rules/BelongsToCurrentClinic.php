<?php

namespace App\Rules;

use App\Models\Clinic;
use App\Services\Configuration\CurrentClinicResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Validates that a foreign key references a row owned by the current clinic (ADR-033).
 */
class BelongsToCurrentClinic implements ValidationRule
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly bool $nullable = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            if ($this->nullable) {
                return;
            }

            $fail('The selected :attribute is invalid.');

            return;
        }

        $clinicId = app(CurrentClinicResolver::class)->resolveId();

        if ($this->modelClass === Clinic::class) {
            if ((int) $value !== $clinicId) {
                $fail('The selected :attribute is invalid.');
            }

            return;
        }

        if (! $this->modelClass::query()->whereKey((int) $value)->where('clinic_id', $clinicId)->exists()) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
