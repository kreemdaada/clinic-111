<?php

namespace Database\Seeders;

use App\Models\Treatment;
use Database\Seeders\Concerns\ResolvesDefaultClinic;
use Illuminate\Database\Seeder;

/**
 * Seeds treatment catalog: lab-cost codes (JOB) and clinical-only codes.
 *
 * All valid parsed codes create work_items; lab_jobs only when has_lab_cost = true.
 *
 * @see database/seeders/README.md
 */
class TreatmentSeeder extends Seeder
{
    use ResolvesDefaultClinic;

    public function run(): void
    {
        $clinic = $this->defaultClinic();

        $labCostNames = [
            'MC' => 'Metal Ceramic Crown',
            'ZIR' => 'Zircon Crown',
            'IMPL-CR' => 'Implant Crown',
            'IMPL-ZIR' => 'Zircon Implant Crown',
            'VENEER' => 'Veneer',
            'POST' => 'Post',
            'ABT' => 'Abutment (the component placed on the implant)',
            'IMPL' => 'Implant',
            'REMOV' => 'Removable Tooth',
        ];

        foreach (array_keys($labCostNames) as $code) {
            Treatment::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $labCostNames[$code] ?? $code,
                    'has_lab_cost' => true,
                    'is_active' => true,
                    'clinic_id' => $clinic->id,
                ],
            );
        }

        $withoutLabCost = [
            'CF' => 'Composite Filling',
            'RCF' => 'Root Canal Filling',
            'SXP' => 'SxP Treatment',
            'AF' => 'Amalgam Filling',
            'RCT' => 'Root Canal Treatment',
            'RE-RCT' => 'Repeat Root Canal Treatment',
            'REPAIR' => 'Repair',
            'REIMPL' => 'Re-implant',
            'PARTIAL' => 'Partial Denture',
            'BLEACHING' => 'Bleaching',
            'EXO' => 'Extraction',
            'APICO' => 'Apicoectomy',
            'BG' => 'Bone Graft',
            'SINUS' => 'Sinus Lift',
        ];

        foreach ($withoutLabCost as $code => $name) {
            Treatment::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'has_lab_cost' => false, 'is_active' => true, 'clinic_id' => $clinic->id],
            );
        }
    }
}
