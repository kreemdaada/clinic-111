<?php

namespace App\Support;

use App\Models\Clinic;

/**
 * Shared clinic JSON shape for API responses (ADR-034).
 */
final class ClinicApiPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function format(Clinic $clinic): array
    {
        $metadata = $clinic->currencyMetadata();

        return [
            'id' => $clinic->id,
            'name' => $clinic->name,
            'code' => $clinic->code,
            'currency' => $clinic->currency,
            'currency_name' => $metadata['name'],
            'currency_symbol' => $metadata['symbol'],
            'currency_precision' => $metadata['precision'],
            'timezone' => $clinic->timezone,
            'country' => $clinic->country,
            'is_active' => $clinic->is_active,
        ];
    }
}
