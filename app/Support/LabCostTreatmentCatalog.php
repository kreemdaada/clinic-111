<?php

namespace App\Support;

use App\Models\Treatment;

/**
 * Resolves lab-cost treatment flags from the database catalog.
 *
 * Runtime business logic must not hardcode treatment codes.
 * Initial seed data lives in {@see \Database\Seeders\TreatmentSeeder}.
 */
final class LabCostTreatmentCatalog
{
    /**
     * Whether the given code creates lab jobs (JOB column).
     *
     * @param  string  $code  Treatment code (case-insensitive).
     */
    public static function isLabCostCode(string $code): bool
    {
        $normalized = strtoupper(trim($code));

        if ($normalized === '') {
            return false;
        }

        $treatment = Treatment::query()->where('code', $normalized)->first();

        if ($treatment === null) {
            return false;
        }

        return $treatment->has_lab_cost;
    }

    /**
     * Active treatment codes that generate lab jobs.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return Treatment::query()
            ->where('has_lab_cost', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->pluck('code')
            ->all();
    }
}
