<?php

namespace App\Support;

/**
 * Treatments that generate JOB (lab cost) in the Original / Server Income report.
 *
 * All other treatments (CF, SxP, RCT, EXO, AF, BLEACHING, …) must not appear
 * in income columns H–T and must not contribute to column G (JOB).
 */
final class LabCostTreatmentCatalog
{
    /** @var array<int, string> */
    public const CODES = [
        'MC',
        'ZIR',
        'IMPL-CR',
        'IMPL-ZIR',
        'VENEER',
        'IMPL',
        'POST',
        'ABT',
        'REMOV',
    ];

    public static function isLabCostCode(string $code): bool
    {
        return in_array(strtoupper(trim($code)), self::CODES, true);
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return self::CODES;
    }
}
