<?php

namespace App\Support;

/**
 * Treatments that must never appear in the Server/Original Income Excel
 * (columns SxP, RCT, CF, EXO, AF, …) and must never contribute to JOB (column G).
 */
final class NonLabIncomeTreatmentCatalog
{
    /** @var array<int, string> */
    public const CODES = [
        'SXP',
        'RCT',
        'CF',
        'EXO',
        'AF',
        'RCF',
        'RE-RCT',
        'BLEACHING',
        'APICO',
        'REPAIR',
        'REIMPL',
        'PARTIAL',
        'BG',
        'SINUS',
    ];

    public static function isNonLabIncomeCode(string $code): bool
    {
        return in_array(strtoupper(trim($code)), self::CODES, true)
            || ! LabCostTreatmentCatalog::isLabCostCode($code);
    }

    public static function countsForJob(string $code): bool
    {
        return LabCostTreatmentCatalog::isLabCostCode($code);
    }
}
