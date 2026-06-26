<?php

namespace App\Support;

use App\Models\DoctorFixedFee;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Ensures only one active fixed fee exists per doctor/treatment and date range.
 */
class DoctorFixedFeeOverlapValidator
{
    public function hasActiveOverlap(
        int $clinicId,
        int $doctorId,
        int $treatmentId,
        ?string $validFrom,
        ?string $validTo,
        ?int $excludeDoctorFixedFeeId = null,
    ): bool {
        $query = DoctorFixedFee::query()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->where('doctor_id', $doctorId)
            ->where('treatment_id', $treatmentId);

        if ($excludeDoctorFixedFeeId !== null) {
            $query->where('id', '!=', $excludeDoctorFixedFeeId);
        }

        $newStart = $this->rangeStart($validFrom);
        $newEnd = $this->rangeEnd($validTo);

        foreach ($query->get() as $existing) {
            $existStart = $this->rangeStart($existing->valid_from?->toDateString());
            $existEnd = $this->rangeEnd($existing->valid_to?->toDateString());

            if ($newStart->lte($existEnd) && $existStart->lte($newEnd)) {
                return true;
            }
        }

        return false;
    }

    private function rangeStart(?string $validFrom): CarbonInterface
    {
        return $validFrom !== null && $validFrom !== ''
            ? Carbon::parse($validFrom)->startOfDay()
            : Carbon::parse('1970-01-01');
    }

    private function rangeEnd(?string $validTo): CarbonInterface
    {
        return $validTo !== null && $validTo !== ''
            ? Carbon::parse($validTo)->endOfDay()
            : Carbon::parse('9999-12-31');
    }
}
