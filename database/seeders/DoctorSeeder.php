<?php

namespace Database\Seeders;

use App\Enums\CommissionType;
use App\Models\Doctor;
use App\Models\Lab;
use Database\Seeders\Concerns\ResolvesDefaultClinic;
use Illuminate\Database\Seeder;

/**
 * Active doctors with commission type, percentage, and default lab.
 *
 * @see database/seeders/README.md
 */
class DoctorSeeder extends Seeder
{
    use ResolvesDefaultClinic;

    public function run(): void
    {
        $clinic = $this->defaultClinic();
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $riyadhLab = Lab::query()->where('code', 'RIYADH_LAB')->firstOrFail();

        Doctor::query()->updateOrCreate(
            ['code' => 'JACK'],
            [
                'name' => 'Dr Jack',
                'commission_type' => CommissionType::Percentage,
                'commission_percentage' => 35,
                'default_lab_id' => $mainLab->id,
                'is_active' => true,
                'clinic_id' => $clinic->id,
            ],
        );

        Doctor::query()->updateOrCreate(
            ['code' => 'RIYAD'],
            [
                'name' => 'Dr Riyad',
                'commission_type' => CommissionType::Percentage,
                'commission_percentage' => 35,
                'default_lab_id' => $riyadhLab->id,
                'is_active' => true,
                'clinic_id' => $clinic->id,
            ],
        );

        Doctor::query()->updateOrCreate(
            ['code' => 'PURIYA'],
            [
                'name' => 'Dr Puriya',
                'commission_type' => CommissionType::Percentage,
                'commission_percentage' => 25,
                'default_lab_id' => $mainLab->id,
                'is_active' => true,
                'clinic_id' => $clinic->id,
            ],
        );

        Doctor::query()->updateOrCreate(
            ['code' => 'WA'],
            [
                'name' => 'Dr Wa',
                'commission_type' => CommissionType::Fixed,
                'commission_percentage' => null,
                'default_lab_id' => $mainLab->id,
                'is_active' => true,
                'clinic_id' => $clinic->id,
            ],
        );
    }
}
