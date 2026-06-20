<?php

namespace Database\Seeders;

use App\Models\Lab;
use Illuminate\Database\Seeder;

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
