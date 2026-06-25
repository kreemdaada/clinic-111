<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\DailyReport;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use App\Services\DailyReport\DailyReportLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_viewer_cannot_create_update_or_delete_reports(): void
    {
        $viewer = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();
        $report = $this->createManualReport();

        $this->actingAs($viewer)
            ->get(route('imports.index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('imports.store'), [])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('daily-report.store'), [
                'doctor_id' => Doctor::query()->where('code', 'JACK')->value('id'),
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('daily-report.destroy', $report))
            ->assertForbidden();
    }

    public function test_accountant_cannot_edit_doctors_or_lab_prices(): void
    {
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'PURIYA')->firstOrFail();
        $labPrice = LabPrice::query()->firstOrFail();

        $this->actingAs($accountant)
            ->get(route('doctors.index'))
            ->assertForbidden();

        $this->actingAs($accountant)
            ->put(route('doctors.update', $doctor), [
                'name' => $doctor->name,
                'commission_type' => 'percentage',
                'commission_percentage' => '99',
                'default_lab_id' => $doctor->default_lab_id,
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->actingAsRole('accountant');

        $this->putJson("/api/admin/lab-prices/{$labPrice->id}", ['unit_cost' => 999])
            ->assertForbidden();
    }

    public function test_admin_can_manage_doctors_and_lab_prices(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'PURIYA')->firstOrFail();
        $labPrice = LabPrice::query()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('doctors.update', $doctor), [
                'name' => $doctor->name,
                'commission_type' => 'percentage',
                'commission_percentage' => '30',
                'default_lab_id' => $doctor->default_lab_id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('doctors.index'));

        $this->assertSame('30.00', (string) $doctor->fresh()->commission_percentage);

        $this->actingAsRole('admin');

        $this->putJson("/api/admin/lab-prices/{$labPrice->id}", ['unit_cost' => 123.45])
            ->assertOk()
            ->assertJsonPath('data.unit_cost', '123.45');
    }

    public function test_approved_report_cannot_be_edited(): void
    {
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $report = $this->createManualReport();
        $report->update(['status' => ReportStatus::Calculated]);

        app(DailyReportLockService::class)->approve($report->fresh(), $admin);

        $doctorId = Doctor::query()->where('code', 'JACK')->value('id');

        $this->actingAs($accountant)
            ->postJson(route('daily-report.rows.save', $report), [
                'doctor_id' => $doctorId,
                'day' => 5,
                'dhs_amount' => '100',
                'treatment_lines' => [
                    ['code' => 'MC', 'quantity' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Approved or locked reports are read-only.');
    }

    public function test_audit_log_is_created_when_commission_changes(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'PURIYA')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('doctors.update', $doctor), [
                'name' => $doctor->name,
                'commission_type' => 'percentage',
                'commission_percentage' => '22',
                'default_lab_id' => $doctor->default_lab_id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('doctors.index'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CommissionChange->value,
            'auditable_type' => $doctor->getMorphClass(),
            'auditable_id' => $doctor->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_soft_deactivate_doctor_instead_of_delete(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->create([
            'name' => 'Dr Soft',
            'code' => 'SOFT',
            'commission_type' => 'percentage',
            'commission_percentage' => 15,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('doctors.destroy', $doctor))
            ->assertRedirect(route('doctors.index'));

        $this->assertDatabaseHas('doctors', [
            'id' => $doctor->id,
            'code' => 'SOFT',
            'is_active' => false,
        ]);
    }

    public function test_unauthenticated_api_requests_are_rejected(): void
    {
        $this->getJson('/api/doctors')->assertUnauthorized();
        $this->postJson('/api/daily-reports/import')->assertUnauthorized();
    }

    private function createManualReport(): DailyReport
    {
        return DailyReport::query()->create([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'Test manual',
            'status' => ReportStatus::Uploaded,
        ]);
    }
}
