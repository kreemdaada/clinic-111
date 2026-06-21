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
    /** @var array<int, string> Treatment codes that create lab jobs and Income H–P counts. */
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

    /**
     * Whether the given code is a lab-cost treatment (counts toward JOB).
     *
     * @param  string  $code  Treatment code (case-insensitive).
     */
    public static function isLabCostCode(string $code): bool
    {
        return in_array(strtoupper(trim($code)), self::CODES, true);
    }

    /**
     * Return all lab-cost treatment codes.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return self::CODES;
    }
}
