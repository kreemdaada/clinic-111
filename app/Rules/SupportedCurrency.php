<?php

namespace App\Rules;

use App\Domain\Currency\CurrencyCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is a supported ISO currency code (ADR-034).
 */
class SupportedCurrency implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! CurrencyCatalog::isSupported($value)) {
            $supported = implode(', ', CurrencyCatalog::codes());
            $fail("The {$attribute} must be a supported currency ({$supported}).");
        }
    }
}
