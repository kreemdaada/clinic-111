<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CommissionType;
use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\AuditLog;
use App\Models\DailyReport;
use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Configuration\ConfigurationDashboardService;
use App\Services\DailyReport\DailyReportLockService;
use App\Support\SecurePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Milestone 13B — exhaustive cross-clinic authorization tests (ADR-033).
 */
class CrossClinicAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $clinic222;

    private User $admin111;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->clinic222 = $this->seedClinic222Tenant();
        $this->admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
    }

    public function test_cross_clinic_daily_report_show_returns_not_found(): void
    {
        $report222 = $this->createClinic222DailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'status' => ReportStatus::Calculated,
        ]);

        $this->actingAs($this->admin111)
            ->get(route('daily-report.edit', $report222))
            ->assertNotFound();
    }

    public function test_cross_clinic_lab_update_returns_not_found(): void
    {
        $this->actingAs($this->admin111)
            ->put(route('labs.update', $this->clinic222['lab']), [
                'name' => 'Hacked Lab',
                'code' => 'C222_LAB',
                'is_active' => '1',
            ])
            ->assertNotFound();
    }

    public function test_cross_clinic_lab_deactivate_returns_not_found(): void
    {
        $this->actingAs($this->admin111)
            ->delete(route('labs.destroy', $this->clinic222['lab']))
            ->assertNotFound();
    }

    public function test_cross_clinic_lab_activate_returns_not_found(): void
    {
        $lab222 = $this->clinic222['lab'];
        $lab222->is_active = false;
        $lab222->save();

        $this->actingAs($this->admin111)
            ->post(route('labs.activate', $lab222))
            ->assertNotFound();
    }

    public function test_cross_clinic_treatment_update_returns_not_found(): void
    {
        $this->actingAs($this->admin111)
            ->put(route('treatments.update', $this->clinic222['treatment']), [
                'code' => 'C222_TX',
                'name' => 'Hacked Treatment',
                'has_lab_cost' => true,
                'is_active' => '1',
            ])
            ->assertNotFound();
    }

    public function test_cross_clinic_treatment_deactivate_returns_not_found(): void
    {
        $this->actingAs($this->admin111)
            ->delete(route('treatments.destroy', $this->clinic222['treatment']))
            ->assertNotFound();
    }

    public function test_cross_clinic_lab_price_update_returns_not_found(): void
    {
        $this->actingAs($this->admin111)
            ->put(route('lab-prices.update', $this->clinic222['price']), [
                'unit_cost' => '999.00',
                'currency' => 'AED',
            ])
            ->assertNotFound();
    }

    public function test_cross_clinic_lab_price_duplicate_returns_not_found(): void
    {
        $this->actingAs($this->admin111)
            ->post(route('lab-prices.duplicate', $this->clinic222['price']))
            ->assertNotFound();
    }

    public function test_cross_clinic_user_update_returns_not_found(): void
    {
        $this->actingAs($this->admin111)
            ->put(route('admin.users.update', $this->clinic222['admin']), [
                'name' => 'Hacked Admin',
                'email' => 'admin@clinic222.test',
                'role' => 'admin',
            ])
            ->assertNotFound();
    }

    public function test_cross_clinic_user_deactivate_returns_not_found(): void
    {
        $this->actingAs($this->admin111)
            ->delete(route('admin.users.destroy', $this->clinic222['admin']))
            ->assertNotFound();
    }

    public function test_cross_clinic_user_password_reset_returns_not_found(): void
    {
        $this->actingAs($this->admin111)
            ->post(route('admin.users.reset-password', $this->clinic222['admin']), [
                'password' => SecurePassword::example(),
                'password_confirmation' => SecurePassword::example(),
            ])
            ->assertNotFound();
    }

    public function test_cross_clinic_clinic_deactivate_returns_not_found(): void
    {
        $this->actingAs($this->admin111)
            ->delete(route('clinics.destroy', $this->clinic222['clinic']))
            ->assertNotFound();
    }

    public function test_cross_clinic_report_approve_returns_not_found(): void
    {
        $report222 = $this->createClinic222DailyReport([
            'report_date' => '2026-07-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Calculated,
        ]);

        $this->actingAs($this->admin111)
            ->post(route('daily-report.approve', $report222))
            ->assertNotFound();
    }

    public function test_cross_clinic_report_unlock_returns_not_found(): void
    {
        $report222 = $this->createClinic222DailyReport([
            'report_date' => '2026-08-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Calculated,
        ]);

        $this->actingAs($this->clinic222['admin']);
        app(DailyReportLockService::class)->approve($report222->fresh(), $this->clinic222['admin']);

        $this->actingAs($this->admin111)
            ->post(route('daily-report.unlock', $report222), [
                'reason' => 'Attempt cross-clinic unlock',
            ])
            ->assertNotFound();
    }

    public function test_cross_clinic_income_export_returns_not_found(): void
    {
        $report222 = $this->createClinic222DailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'status' => ReportStatus::Calculated,
        ]);

        $this->actingAs($this->admin111)
            ->get(route('imports.income', $report222))
            ->assertNotFound();
    }

    public function test_cross_clinic_doctor_treatments_returns_not_found(): void
    {
        $this->actingAs($this->admin111)
            ->get(route('doctors.treatments', $this->clinic222['doctor']))
            ->assertNotFound();
    }

    public function test_cross_clinic_foreign_doctor_id_in_report_preview_returns_not_found(): void
    {
        $report111 = $this->createDailyReport([
            'report_date' => '2026-09-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);

        $this->actingAs($this->admin111)
            ->postJson(route('daily-report.preview', $report111), [
                'doctor_id' => $this->clinic222['doctor']->id,
                'day' => 1,
                'treatment_lines' => [['code' => 'BG', 'quantity' => 1]],
                'dhs_amount' => '100',
            ])
            ->assertNotFound();
    }

    public function test_foreign_lab_id_on_doctor_create_is_rejected(): void
    {
        $this->actingAs($this->admin111)
            ->post(route('doctors.store'), [
                'name' => 'Cross Lab Doctor',
                'code' => 'X_LAB_DOC',
                'commission_type' => CommissionType::Percentage->value,
                'commission_percentage' => 25,
                'default_lab_id' => $this->clinic222['lab']->id,
            ])
            ->assertSessionHasErrors('default_lab_id');
    }

    public function test_foreign_lab_and_treatment_on_lab_price_create_is_rejected(): void
    {
        $this->actingAs($this->admin111)
            ->post(route('lab-prices.store'), [
                'lab_id' => $this->clinic222['lab']->id,
                'treatment_id' => $this->clinic222['treatment']->id,
                'unit_cost' => '50.00',
                'currency' => 'AED',
            ])
            ->assertSessionHasErrors(['lab_id', 'treatment_id']);
    }

    public function test_configuration_dashboard_audit_activity_is_clinic_scoped(): void
    {
        AuditLog::query()->create([
            'clinic_id' => $this->clinic222['clinic']->id,
            'user_id' => $this->clinic222['admin']->id,
            'action' => AuditAction::DoctorCreated,
            'auditable_type' => (new Doctor)->getMorphClass(),
            'auditable_id' => $this->clinic222['doctor']->id,
            'new_values' => ['code' => 'C222_DOC'],
        ]);

        $activity111 = (function () {
            $this->actingAs($this->admin111);

            return app(ConfigurationDashboardService::class)->recentActivity(50);
        })();
        $targets111 = collect($activity111)->pluck('target')->implode(' ');

        $this->assertStringNotContainsString('C222_DOC', $targets111);

        $this->actingAs($this->clinic222['admin']);
        $activity222 = app(ConfigurationDashboardService::class)->recentActivity(50);
        $targets222 = collect($activity222)->pluck('target')->implode(' ');

        $this->assertStringContainsString('C222_DOC', $targets222);
    }

    public function test_clinic_admin_cannot_create_lab_price_with_foreign_doctor(): void
    {
        $fixedDoctor = Doctor::query()->create([
            'clinic_id' => $this->clinic222['clinic']->id,
            'name' => 'Fixed Fee Doctor',
            'code' => 'C222_FIXED',
            'commission_type' => CommissionType::Fixed,
            'default_lab_id' => $this->clinic222['lab']->id,
            'is_active' => true,
        ]);

        $lab111 = Lab::query()->where('clinic_id', $this->clinic111()->id)->firstOrFail();
        $treatment111 = Treatment::query()->where('clinic_id', $this->clinic111()->id)->firstOrFail();

        $this->actingAs($this->admin111)
            ->post(route('lab-prices.store'), [
                'lab_id' => $lab111->id,
                'treatment_id' => $treatment111->id,
                'doctor_id' => $fixedDoctor->id,
                'unit_cost' => '50.00',
                'currency' => 'AED',
            ])
            ->assertSessionHasErrors('doctor_id');
    }

    public function test_cross_clinic_fixed_fee_update_returns_not_found(): void
    {
        $fee = DoctorFixedFee::query()->create([
            'clinic_id' => $this->clinic222['clinic']->id,
            'doctor_id' => $this->clinic222['doctor']->id,
            'treatment_id' => $this->clinic222['treatment']->id,
            'fee_amount' => '200.00',
            'currency' => 'AED',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin111)
            ->put(route('doctor-fixed-fees.update', $fee), [
                'fee_amount' => '999.00',
                'currency' => 'AED',
            ])
            ->assertNotFound();
    }
}
