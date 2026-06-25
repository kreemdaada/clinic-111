<?php

namespace App\Support;

/**
 * Treatments excluded from Income Excel columns and JOB totals.
 *
 * Derived from the database `has_lab_cost` flag — not a hardcoded list.
 */
final class NonLabIncomeTreatmentCatalog
{
    /**
     * Whether a treatment code is excluded from Income columns and JOB.
     *
     * @param  string  $code  Treatment code from parser or database.
     */
    public static function isNonLabIncomeCode(string $code): bool
    {
        return ! LabCostTreatmentCatalog::isLabCostCode($code);
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
