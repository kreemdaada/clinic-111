<?php

namespace App\Support;

/**
 * Treatments that must never appear in the Server/Original Income Excel
 * (columns SxP, RCT, CF, EXO, AF, …) and must never contribute to JOB (column G).
 */
final class NonLabIncomeTreatmentCatalog
{
    /** @var array<int, string> Known non-lab treatment codes from daily report text. */
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

    /**
     * Whether a treatment code is excluded from Income columns and JOB.
     *
     * Returns true for known non-lab codes or any code not in {@see LabCostTreatmentCatalog}.
     *
     * @param  string  $code  Treatment code from parser or database.
     */
    public static function isNonLabIncomeCode(string $code): bool
    {
        return in_array(strtoupper(trim($code)), self::CODES, true)
            || ! LabCostTreatmentCatalog::isLabCostCode($code);
    }

    /**
     * Whether a treatment code should contribute to column G (JOB / lab cost).
     *
     * @param  string  $code  Treatment code from parser or database.
     */
    public static function countsForJob(string $code): bool
    {
        return LabCostTreatmentCatalog::isLabCostCode($code);
    }
}
