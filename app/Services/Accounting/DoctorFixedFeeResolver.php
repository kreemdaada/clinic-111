<?php

namespace App\Services\Accounting;

use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Treatment;
use Carbon\CarbonInterface;

/**
 * Resolves the effective fixed fee for a doctor/treatment on a given date.
 *
 * Reads active rows with optional `valid_from` / `valid_to` windows.
 */
class DoctorFixedFeeResolver
{
    public function resolve(
        Doctor $doctor,
        Treatment $treatment,
        ?CarbonInterface $effectiveDate = null,
    ): ?DoctorFixedFee {
        if ($effectiveDate === null) {
            $effectiveDate = now();
        }

        return DoctorFixedFee::query()
            ->where('doctor_id', $doctor->id)
            ->where('treatment_id', $treatment->id)
            ->where('is_active', true)
            ->where(function ($builder) use ($effectiveDate) {
                $builder
                    ->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $effectiveDate->toDateString());
            })
            ->where(function ($builder) use ($effectiveDate) {
                $builder
                    ->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $effectiveDate->toDateString());
            })
            ->first();
    }
}
