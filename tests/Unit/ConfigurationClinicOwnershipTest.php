<?php

namespace Tests\Unit;

use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationClinicOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_seeders_assign_all_configuration_records_to_clinic_111(): void
    {
        $clinic = $this->clinic111();

        $this->assertSame(
            User::query()->count(),
            User::query()->where('clinic_id', $clinic->id)->count(),
        );
        $this->assertSame(
            Doctor::query()->count(),
            Doctor::query()->where('clinic_id', $clinic->id)->count(),
        );
        $this->assertSame(
            Lab::query()->count(),
            Lab::query()->where('clinic_id', $clinic->id)->count(),
        );
        $this->assertSame(
            Treatment::query()->count(),
            Treatment::query()->where('clinic_id', $clinic->id)->count(),
        );
        $this->assertSame(
            LabPrice::query()->count(),
            LabPrice::query()->where('clinic_id', $clinic->id)->count(),
        );
        $this->assertSame(
            DoctorFixedFee::query()->count(),
            DoctorFixedFee::query()->where('clinic_id', $clinic->id)->count(),
        );
    }

    public function test_migration_backfills_existing_rows_to_clinic_111(): void
    {
        $clinic = $this->clinic111();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@clinic.test',
            'clinic_id' => $clinic->id,
        ]);
        $this->assertDatabaseHas('doctors', [
            'code' => 'JACK',
            'clinic_id' => $clinic->id,
        ]);
        $this->assertDatabaseHas('labs', [
            'code' => 'MAIN_LAB',
            'clinic_id' => $clinic->id,
        ]);
        $this->assertDatabaseHas('treatments', [
            'code' => 'ZIR',
            'clinic_id' => $clinic->id,
        ]);
    }

    public function test_clinic_id_is_required_on_configuration_tables(): void
    {
        $this->expectException(QueryException::class);

        Lab::withoutEvents(function () {
            Lab::query()->insert([
                'name' => 'Missing Clinic',
                'code' => 'NO_CLINIC',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
