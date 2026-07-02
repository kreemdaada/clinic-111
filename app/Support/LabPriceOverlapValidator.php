<?php

namespace App\Support;

use App\Models\LabPrice;

/**
 * Ensures only one active lab price exists per clinic/lab/treatment/doctor/currency scope.
 */
class LabPriceOverlapValidator
{
    public function hasActiveOverlap(
        int $clinicId,
        int $labId,
        int $treatmentId,
        ?int $doctorId,
        string $currency,
        ?int $excludeLabPriceId = null,
    ): bool {
        $query = LabPrice::query()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->where('lab_id', $labId)
            ->where('treatment_id', $treatmentId)
            ->where('currency', strtoupper($currency));

        if ($doctorId === null) {
            $query->whereNull('doctor_id');
        } else {
            $query->where('doctor_id', $doctorId);
        }

        if ($excludeLabPriceId !== null) {
            $query->where('id', '!=', $excludeLabPriceId);
        }

        return $query->exists();
    }
}
