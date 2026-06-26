<?php

namespace Database\Seeders;

use App\Models\Lab;
use Database\Seeders\Concerns\ResolvesDefaultClinic;
use Illuminate\Database\Seeder;

/**
 * MAIN_LAB and RIYADH_LAB reference labs.
 *
 * @see database/seeders/README.md
 */
class LabSeeder extends Seeder
{
    use ResolvesDefaultClinic;

    public function run(): void
    {
        $clinic = $this->defaultClinic();

        Lab::query()->updateOrCreate(
            ['code' => 'MAIN_LAB'],
            ['name' => 'Main Lab', 'is_active' => true, 'clinic_id' => $clinic->id],
        );

        Lab::query()->updateOrCreate(
            ['code' => 'RIYADH_LAB'],
            ['name' => 'Riyadh Lab', 'is_active' => true, 'clinic_id' => $clinic->id],
        );
    }
}
