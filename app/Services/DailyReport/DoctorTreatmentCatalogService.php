<?php

namespace App\Services\DailyReport;

use App\Enums\CommissionType;
use App\Models\Doctor;
use App\Models\DoctorLabBilling;
use App\Models\Lab;
use App\Models\Treatment;
use App\Services\Accounting\DoctorFixedFeeResolver;
use App\Services\Accounting\LabBillingResolver;
use App\Services\Accounting\LabPriceResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Returns treatments a doctor may enter in the V2 daily report editor.
 */
class DoctorTreatmentCatalogService
{
    public function __construct(
        private readonly LabBillingResolver $labBillingResolver,
        private readonly LabPriceResolver $labPriceResolver,
        private readonly DoctorFixedFeeResolver $doctorFixedFeeResolver,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forDoctor(Doctor $doctor, ?Carbon $workDate = null): Collection
    {
        $workDate ??= now()->startOfDay();

        $this->ensureLabBillingSynced($doctor);

        return $this->allowedTreatments($doctor)
            ->map(function (Treatment $treatment) use ($doctor, $workDate) {
                $fixedFee = $this->fixedFeeFor($doctor, $treatment, $workDate);
                $billsLab = $this->labBillingResolver->shouldBillLabJob($doctor, $treatment);
                $labPrice = null;

                if ($billsLab) {
                    $activeLabs = Lab::query()
                        ->where('clinic_id', $doctor->clinic_id)
                        ->where('is_active', true)
                        ->get();
                    $resolved = $this->labPriceResolver->resolveWithLabFallback(
                        $doctor,
                        $treatment,
                        $activeLabs,
                        $workDate,
                    );

                    if ($resolved !== null) {
                        $labPrice = [
                            'unit_cost' => (string) $resolved['price']->unit_cost,
                            'unit_cost_aed' => (string) $resolved['price']->unit_cost,
                            'currency' => $resolved['price']->currency,
                            'lab_code' => $resolved['lab']->code,
                        ];
                    }
                }

                return [
                    'id' => $treatment->id,
                    'code' => $treatment->code,
                    'name' => $treatment->name,
                    'has_lab_cost' => $treatment->has_lab_cost,
                    'bills_lab_job' => $billsLab,
                    'fixed_fee' => $fixedFee,
                    'lab_price' => $labPrice,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, Treatment>
     */
    private function allowedTreatments(Doctor $doctor): Collection
    {
        if ($doctor->commission_type === CommissionType::Fixed) {
            return Treatment::query()
                ->where('clinic_id', $doctor->clinic_id)
                ->where('is_active', true)
                ->whereHas('doctorFixedFees', fn ($query) => $query
                    ->where('doctor_id', $doctor->id)
                    ->where('is_active', true))
                ->orderBy('code')
                ->get();
        }

        return Treatment::query()
            ->where('clinic_id', $doctor->clinic_id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    private function ensureLabBillingSynced(Doctor $doctor): void
    {
        if ($doctor->commission_type !== CommissionType::Percentage) {
            return;
        }

        $labCostTreatmentIds = Treatment::query()
            ->where('clinic_id', $doctor->clinic_id)
            ->where('has_lab_cost', true)
            ->where('is_active', true)
            ->pluck('id');

        foreach ($labCostTreatmentIds as $treatmentId) {
            DoctorLabBilling::query()->firstOrCreate(
                [
                    'doctor_id' => $doctor->id,
                    'treatment_id' => $treatmentId,
                ],
                [
                    'bill_lab_job' => true,
                ],
            );
        }
    }

    /**
     * @return array{amount: string, currency: string}|null
     */
    private function fixedFeeFor(Doctor $doctor, Treatment $treatment, Carbon $workDate): ?array
    {
        if ($doctor->commission_type !== CommissionType::Fixed) {
            return null;
        }

        $fee = $this->doctorFixedFeeResolver->resolve($doctor, $treatment, $workDate);

        if ($fee === null) {
            return null;
        }

        return [
            'amount' => (string) $fee->fee_amount,
            'currency' => $fee->currency,
        ];
    }
}
