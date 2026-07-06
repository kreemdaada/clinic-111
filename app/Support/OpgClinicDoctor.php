<?php

namespace App\Support;

/**
 * Clinic-level doctor attribution for imported OPG sections (not a treating dentist).
 */
final class OpgClinicDoctor
{
    public const CODE = 'CLINIC_OPG';

    public const NAME = 'Clinic OPG';

    public const IMPORT_LABEL = 'CLINIC OPG';

    public static function matches(?string $code, ?string $label): bool
    {
        foreach ([$code, $label] as $value) {
            $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $value) ?? ''));

            if ($normalized === self::CODE || $normalized === str_replace('_', ' ', self::CODE)) {
                return true;
            }
        }

        return strtoupper(trim((string) $label)) === strtoupper(self::IMPORT_LABEL);
    }
}
