<?php

namespace Database\Seeders;

use App\Models\Lab;
use Illuminate\Database\Seeder;

/**
 * MAIN_LAB and RIYADH_LAB reference labs.
 *
 * @see database/seeders/README.md
 */
class LabSeeder extends Seeder
{
    public function run(): void
    {
        Lab::query()->updateOrCreate(
            ['code' => 'MAIN_LAB'],
            ['name' => 'Main Lab', 'is_active' => true],
        );

        Lab::query()->updateOrCreate(
            ['code' => 'RIYADH_LAB'],
            ['name' => 'Riyadh Lab', 'is_active' => true],
        );
    }
}
