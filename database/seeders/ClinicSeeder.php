<?php

namespace Database\Seeders;

use App\Models\Clinic;
use Illuminate\Database\Seeder;

/**
 * Seeds the default Clinic 111 tenant (ADR-026).
 *
 * @see database/seeders/README.md
 */
class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        Clinic::query()->updateOrCreate(
            ['code' => 'CLINIC_111'],
            [
                'name' => 'Clinic 111',
                'currency' => 'AED',
                'timezone' => 'Asia/Dubai',
                'country' => 'United Arab Emirates',
                'is_active' => true,
            ],
        );
    }
}
