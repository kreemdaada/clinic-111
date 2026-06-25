<?php

namespace App\Support;

use App\Models\LabPrice;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Ensures only one active lab price exists per lab/treatment/doctor scope and date range.
 */
class LabPriceOverlapValidator
{
    public function hasActiveOverlap(
        int $labId,
        int $treatmentId,
        ?int $doctorId,
        ?string $validFrom,
        ?string $validTo,
        ?int $excludeLabPriceId = null,
    ): bool {
        $query = LabPrice::query()
            ->where('is_active', true)
            ->where('lab_id', $labId)
            ->where('treatment_id', $treatmentId);

        if ($doctorId === null) {
            $query->whereNull('doctor_id');
        } else {
            $query->where('doctor_id', $doctorId);
        }

        if ($excludeLabPriceId !== null) {
            $query->where('id', '!=', $excludeLabPriceId);
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
