<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Root seeder — runs all reference data in dependency order.
 *
 * @see database/seeders/README.md
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            LabSeeder::class,
            DoctorSeeder::class,
            TreatmentSeeder::class,
            LabPriceSeeder::class,
            DoctorLabBillingSeeder::class,
            DoctorFixedFeeSeeder::class,
            DoctorIncomeExportProfileSeeder::class,
            UserSeeder::class,
        ]);
    }
}
