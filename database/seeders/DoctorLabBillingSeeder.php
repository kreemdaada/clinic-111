<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorLabBilling;
use App\Models\Treatment;
use App\Support\LabCostTreatmentCatalog;
use Illuminate\Database\Seeder;

/**
 * Per-doctor lab JOB rules: which treatments deduct lab cost from doctor income.
 *
 * Jack / Riyad: all lab-cost treatments.
 * Puriya: MC, ZIR, POST, REMOV only.
 * Wa: none (fixed fees only).
 *
 * @see database/seeders/README.md
 */
class DoctorLabBillingSeeder extends Seeder
{
    public function run(): void
    {
        $fullLabBillingDoctors = ['JACK', 'RIYAD'];
        $puriyaLabCodes = ['MC', 'ZIR', 'POST', 'REMOV'];

        foreach ($fullLabBillingDoctors as $doctorCode) {
            $doctor = Doctor::query()->where('code', $doctorCode)->firstOrFail();

            foreach (LabCostTreatmentCatalog::codes() as $treatmentCode) {
                $this->upsertBilling($doctor->id, $treatmentCode, true);
            }
        }

        $doctorPuriya = Doctor::query()->where('code', 'PURIYA')->firstOrFail();

        foreach ($puriyaLabCodes as $treatmentCode) {
            $this->upsertBilling($doctorPuriya->id, $treatmentCode, true);
        }
    }

    private function upsertBilling(int $doctorId, string $treatmentCode, bool $billLabJob): void
    {
        $treatment = Treatment::query()->where('code', $treatmentCode)->firstOrFail();

        DoctorLabBilling::query()->updateOrCreate(
            [
                'doctor_id' => $doctorId,
                'treatment_id' => $treatment->id,
            ],
            [
                'bill_lab_job' => $billLabJob,
            ],
        );
    }
}
