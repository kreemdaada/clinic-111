<?php

namespace App\Services\Accounting;

use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resolves lab unit prices using doctor-specific overrides with default fallback.
 */
class LabPriceResolver
{
    public function resolve(
        Doctor $doctor,
        Treatment $treatment,
        Lab $lab,
        ?CarbonInterface $effectiveDate = null,
    ): ?LabPrice {
        if ($effectiveDate === null) {
            $effectiveDate = now();
        }

        $doctorSpecificPrice = $this->findPrice($doctor->id, $treatment->id, $lab->id, $effectiveDate);

        if ($doctorSpecificPrice !== null) {
            return $doctorSpecificPrice;
        }

        return $this->findPrice(null, $treatment->id, $lab->id, $effectiveDate);
    }

    private function findPrice(
        ?int $doctorId,
        int $treatmentId,
        int $labId,
        CarbonInterface $effectiveDate,
    ): ?LabPrice {
        $query = LabPrice::query()
            ->where('treatment_id', $treatmentId)
            ->where('lab_id', $labId)
            ->where(function ($builder) use ($doctorId) {
                if ($doctorId === null) {
                    $builder->whereNull('doctor_id');
                } else {
                    $builder->where('doctor_id', $doctorId);
                }
            })
            ->where(function ($builder) use ($effectiveDate) {
                $builder
                    ->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $effectiveDate->toDateString());
            })
            ->where(function ($builder) use ($effectiveDate) {
                $builder
                    ->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $effectiveDate->toDateString());
            });

        return $query->first();
    }

    public function resolveLabForDoctor(Doctor $doctor, Collection $activeLabs): Lab
    {
        if ($doctor->default_lab_id !== null) {
            $doctorLab = $activeLabs->firstWhere('id', $doctor->default_lab_id);

            if ($doctorLab !== null) {
                return $doctorLab;
            }
        }

        return $activeLabs->first();
    }
}
