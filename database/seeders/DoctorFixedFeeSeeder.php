<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Treatment;
use Database\Seeders\Concerns\ResolvesDefaultClinic;
use Illuminate\Database\Seeder;

/**
 * Fixed per-procedure fees for Dr Wa (IMPL, BG, SINUS).
 *
 * @see database/seeders/README.md
 */
class DoctorFixedFeeSeeder extends Seeder
{
    use ResolvesDefaultClinic;

    public function run(): void
    {
        $clinic = $this->defaultClinic();
        $doctorWa = Doctor::query()->where('code', 'WA')->firstOrFail();

        $fixedFees = [
            'IMPL' => ['fee_amount' => 500, 'currency' => 'AED'],
            'BG' => ['fee_amount' => 200, 'currency' => 'USD'],
            'SINUS' => ['fee_amount' => 300, 'currency' => 'USD'],
        ];

        foreach ($fixedFees as $treatmentCode => $feeData) {
            $treatment = Treatment::query()->where('code', $treatmentCode)->firstOrFail();

            DoctorFixedFee::query()->updateOrCreate(
                [
                    'doctor_id' => $doctorWa->id,
                    'treatment_id' => $treatment->id,
                ],
                [
                    'fee_amount' => $feeData['fee_amount'],
                    'currency' => $feeData['currency'],
                    'is_active' => true,
                    'clinic_id' => $clinic->id,
                ],
            );
        }
    }
}
