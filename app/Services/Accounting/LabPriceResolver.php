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
 *
 * Lookup order: doctor-specific price → default price (`doctor_id IS NULL`).
 * Both respect `valid_from` / `valid_to` when set.
 */
class LabPriceResolver
{
    /**
     * Find the effective lab price for a doctor/treatment/lab on a given date.
     *
     * @param  Doctor  $doctor  Row's doctor (may have override prices).
     * @param  Treatment  $treatment  Treatment with lab cost.
     * @param  Lab  $lab  Lab to price against.
     * @param  CarbonInterface|null  $effectiveDate  Work date (defaults to now).
     * @return LabPrice|null Matching price row or null if none configured.
     */
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

    /**
     * Query one price row for optional doctor scope and date validity window.
     *
     * @param  int|null  $doctorId  Doctor ID or null for default price.
     * @param  int  $treatmentId  Treatment FK.
     * @param  int  $labId  Lab FK.
     * @param  CarbonInterface  $effectiveDate  Date price must be valid for.
     * @return LabPrice|null First matching row.
     */
    private function findPrice(
        ?int $doctorId,
        int $treatmentId,
        int $labId,
        CarbonInterface $effectiveDate,
    ): ?LabPrice {
        $query = LabPrice::query()
            ->where('treatment_id', $treatmentId)
            ->where('lab_id', $labId)
            ->where('is_active', true)
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

    /**
     * Pick the lab used for JOB calculation for this doctor.
     *
     * Uses `doctors.default_lab_id` when set and active; otherwise first active lab.
     *
     * @param  Doctor  $doctor  Doctor owning the work row.
     * @param  Collection<int, Lab>  $activeLabs  Preloaded active labs collection.
     */
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

    /**
     * Resolve lab + price, falling back to MAIN_LAB when the doctor's lab has no price row.
     *
     * Dr Riyad uses RIYADH_LAB for ZIR / IMPL-ZIR / POST overrides; other treatments may price on MAIN_LAB.
     *
     * @return array{lab: Lab, price: LabPrice}|null
     */
    public function resolveWithLabFallback(
        Doctor $doctor,
        Treatment $treatment,
        Collection $activeLabs,
        ?CarbonInterface $effectiveDate = null,
    ): ?array {
        $primaryLab = $this->resolveLabForDoctor($doctor, $activeLabs);
        $price = $this->resolve($doctor, $treatment, $primaryLab, $effectiveDate);

        if ($price !== null) {
            return ['lab' => $primaryLab, 'price' => $price];
        }

        $mainLab = $activeLabs->firstWhere('code', 'MAIN_LAB');

        if ($mainLab !== null && $mainLab->id !== $primaryLab->id) {
            $price = $this->resolve($doctor, $treatment, $mainLab, $effectiveDate);

            if ($price !== null) {
                return ['lab' => $mainLab, 'price' => $price];
            }
        }

        return null;
    }
}
