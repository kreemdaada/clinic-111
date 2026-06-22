<?php

namespace App\Services\Accounting;

use App\Models\Doctor;
use App\Models\DoctorLabBilling;
use App\Models\Treatment;
use Illuminate\Support\Collection;

/**
 * Decides whether a doctor's work item should generate a lab job (JOB column).
 *
 * Requires `treatments.has_lab_cost = true` and an explicit `doctor_lab_billings` row
 * with `bill_lab_job = true`.
 */
class LabBillingResolver
{
    /** @var array<int, Collection<int, DoctorLabBilling>> */
    private array $billingsByDoctorId = [];

    public function shouldBillLabJob(Doctor $doctor, Treatment $treatment): bool
    {
        if (! $treatment->has_lab_cost) {
            return false;
        }

        $billing = $this->billingsForDoctor($doctor)->get($treatment->id);

        return $billing !== null && $billing->bill_lab_job;
    }

    /**
     * @return Collection<int, DoctorLabBilling> treatment_id → billing row
     */
    private function billingsForDoctor(Doctor $doctor): Collection
    {
        if (! array_key_exists($doctor->id, $this->billingsByDoctorId)) {
            $doctor->loadMissing('doctorLabBillings');

            $this->billingsByDoctorId[$doctor->id] = $doctor->doctorLabBillings->keyBy('treatment_id');
        }

        return $this->billingsByDoctorId[$doctor->id];
    }
}
