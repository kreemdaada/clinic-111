<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use Illuminate\Database\Seeder;

/**
 * Default lab unit prices (AED) and Dr Riyad overrides for ZIR / IMPL-ZIR.
 *
 * REMOV = 100 AED. Prices consumed by LabJobCalculationService via LabPriceResolver.
 *
 * @see database/seeders/README.md
 */
class LabPriceSeeder extends Seeder
{
    public function run(): void
    {
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $riyadhLab = Lab::query()->where('code', 'RIYADH_LAB')->firstOrFail();
        $doctorRiyad = Doctor::query()->where('code', 'RIYAD')->firstOrFail();

        $defaultPrices = [
            'MC' => ['unit_cost' => 105, 'lab_id' => $mainLab->id],
            'ZIR' => ['unit_cost' => 360, 'lab_id' => $mainLab->id],
            'IMPL-CR' => ['unit_cost' => 160, 'lab_id' => $mainLab->id],
            'IMPL-ZIR' => ['unit_cost' => 460, 'lab_id' => $mainLab->id],
            'VENEER' => ['unit_cost' => 360, 'lab_id' => $mainLab->id],
            'POST' => ['unit_cost' => 55, 'lab_id' => $mainLab->id],
            'ABT' => ['unit_cost' => 511, 'lab_id' => $mainLab->id],
            'IMPL' => ['unit_cost' => 1000, 'lab_id' => $mainLab->id],
            'REMOV' => ['unit_cost' => 100, 'lab_id' => $mainLab->id],
        ];

        foreach ($defaultPrices as $treatmentCode => $priceData) {
            $treatment = Treatment::query()->where('code', $treatmentCode)->firstOrFail();

            LabPrice::query()->updateOrCreate(
                [
                    'lab_id' => $priceData['lab_id'],
                    'treatment_id' => $treatment->id,
                    'doctor_id' => null,
                ],
                [
                    'unit_cost' => $priceData['unit_cost'],
                    'currency' => 'AED',
                ],
            );
        }

        $riyadOverrides = [
            'ZIR' => ['unit_cost' => 400, 'lab_id' => $riyadhLab->id],
            'IMPL-ZIR' => ['unit_cost' => 500, 'lab_id' => $riyadhLab->id],
        ];

        foreach ($riyadOverrides as $treatmentCode => $priceData) {
            $treatment = Treatment::query()->where('code', $treatmentCode)->firstOrFail();

            LabPrice::query()->updateOrCreate(
                [
                    'lab_id' => $priceData['lab_id'],
                    'treatment_id' => $treatment->id,
                    'doctor_id' => $doctorRiyad->id,
                ],
                [
                    'unit_cost' => $priceData['unit_cost'],
                    'currency' => 'AED',
                ],
            );
        }
    }
}
