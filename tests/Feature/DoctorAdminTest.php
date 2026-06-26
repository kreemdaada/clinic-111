<?php

namespace Tests\Feature;

use App\Enums\ReportSourceType;
use App\Enums\UserRole;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_doctor_commission_percentage(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $puriya = Doctor::query()->where('code', 'PURIYA')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('doctors.update', $puriya), [
                'name' => 'Dr Puriya',
                'commission_type' => 'percentage',
                'commission_percentage' => '25',
                'default_lab_id' => $puriya->default_lab_id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('doctors.index'))
            ->assertSessionHas('success');

        $this->assertSame('25.00', (string) $puriya->fresh()->commission_percentage);
    }

    public function test_accountant_cannot_access_doctor_admin_page(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('doctors.index'))
            ->assertForbidden();
    }

    public function test_admin_deactivates_doctor_with_report_entries(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'PURIYA')->firstOrFail();
        $report = DailyReport::query()->create([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'test',
            'status' => 'uploaded',
        ]);

        DailyWorkRow::query()->create([
            'daily_report_id' => $report->id,
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
            'paid_total_aed' => '100.00',
        ]);

        $this->actingAs($admin)
            ->delete(route('doctors.destroy', $doctor))
            ->assertRedirect(route('doctors.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('doctors', [
            'id' => $doctor->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_deactivates_doctor_without_report_entries(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->create($this->withClinicId([
            'name' => 'Dr Temp',
            'code' => 'TEMP',
            'commission_type' => 'percentage',
            'commission_percentage' => 10,
            'is_active' => true,
        ]));

        $this->actingAs($admin)
            ->delete(route('doctors.destroy', $doctor))
            ->assertRedirect(route('doctors.index'));

        $this->assertDatabaseHas('doctors', [
            'id' => $doctor->id,
            'is_active' => false,
        ]);
    }

    public function test_inactive_doctors_are_hidden_from_daily_report_editor(): void
    {
        $this->seed();

        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'PURIYA')->firstOrFail();
        $doctor->update(['is_active' => false]);

        $report = DailyReport::query()->create([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'test',
            'status' => 'uploaded',
        ]);

        $this->actingAs($accountant)
            ->get(route('daily-report.edit', $report))
            ->assertOk()
            ->assertDontSee('>PURIYA<');
    }
}
