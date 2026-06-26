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

        $doctorSpecificPrice = $this->findPrice($doctor->id, $treatment->id, $lab->id, $effectiveDate, $doctor->clinic_id);

        if ($doctorSpecificPrice !== null) {
            return $doctorSpecificPrice;
        }

        return $this->findPrice(null, $treatment->id, $lab->id, $effectiveDate, $doctor->clinic_id);
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
        int $clinicId,
    ): ?LabPrice {
        $query = LabPrice::query()
            ->where('clinic_id', $clinicId)
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
                $date = $effectiveDate->toDateString();
                $builder
                    ->whereNull('valid_from')
                    ->orWhereDate('valid_from', '<=', $date);
            })
            ->where(function ($builder) use ($effectiveDate) {
                $date = $effectiveDate->toDateString();
                $builder
                    ->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $date);
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
     * Resolve lab + price, trying the doctor's primary lab first, then any other active clinic lab.
     *
     * Clinic 111: Dr Riyad's RIYADH_LAB price wins on primary; other treatments may resolve on MAIN_LAB.
     * New clinics: onboarding labs use `{CLINIC_CODE}_MAIN_LAB`, not a hardcoded MAIN_LAB code.
     *
     * @return array{lab: Lab, price: LabPrice}|null
     */
    public function resolveWithLabFallback(
        Doctor $doctor,
        Treatment $treatment,
        Collection $activeLabs,
        ?CarbonInterface $effectiveDate = null,
    ): ?array {
        if ($activeLabs->isEmpty()) {
            return null;
        }

        $primaryLab = $this->resolveLabForDoctor($doctor, $activeLabs);
        $price = $this->resolve($doctor, $treatment, $primaryLab, $effectiveDate);

        if ($price !== null) {
            return ['lab' => $primaryLab, 'price' => $price];
        }

        foreach ($activeLabs as $lab) {
            if ($lab->id === $primaryLab->id) {
                continue;
            }

            $price = $this->resolve($doctor, $treatment, $lab, $effectiveDate);

            if ($price !== null) {
                return ['lab' => $lab, 'price' => $price];
            }
        }

        return null;
    }
}
