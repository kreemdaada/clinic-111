<?php

namespace Tests\Unit;

use App\Enums\AuditAction;
use App\Models\Clinic;
use App\Models\User;
use App\Services\Configuration\ClinicManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClinicManagementService $clinicManagementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->clinicManagementService = app(ClinicManagementService::class);
    }

    public function test_create_clinic_uppercases_code_and_currency_and_logs_audit(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $this->actingAs($admin);

        $clinic = $this->clinicManagementService->create([
            'name' => 'Service Clinic',
            'code' => 'svc_clinic',
            'currency' => 'aed',
            'timezone' => 'Asia/Dubai',
            'country' => 'United Arab Emirates',
        ]);

        $this->assertSame('SVC_CLINIC', $clinic->code);
        $this->assertSame('AED', $clinic->currency);
        $this->assertTrue($clinic->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ClinicCreated->value,
            'auditable_id' => $clinic->id,
        ]);
    }

    public function test_deactivate_sets_is_active_false_without_deleting_row(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $clinic = Clinic::query()->where('code', 'CLINIC_111')->firstOrFail();
        $this->actingAs($admin);

        $deactivated = $this->clinicManagementService->deactivate($clinic);

        $this->assertFalse($deactivated->is_active);
        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ClinicDeactivated->value,
            'auditable_id' => $clinic->id,
        ]);
    }

    public function test_deactivate_other_clinic_returns_not_found(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $clinic = Clinic::query()->create([
            'name' => 'Deactivate Service Clinic',
            'code' => 'DEACT_SVC',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'United Arab Emirates',
        ]);
        $this->actingAs($admin);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $this->clinicManagementService->deactivate($clinic);
    }

    public function test_activate_sets_is_active_true_and_logs_audit(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $clinic = Clinic::query()->where('code', 'CLINIC_111')->firstOrFail();
        $clinic->is_active = false;
        $clinic->save();
        $this->actingAs($admin);

        $activated = $this->clinicManagementService->activate($clinic);

        $this->assertTrue($activated->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ClinicActivated->value,
            'auditable_id' => $clinic->id,
        ]);
    }

    public function test_update_name_logs_clinic_updated_audit(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $clinic = Clinic::query()->where('code', 'CLINIC_111')->firstOrFail();
        $this->actingAs($admin);

        $this->clinicManagementService->update($clinic, [
            'name' => 'Clinic 111 Renamed',
            'code' => 'CLINIC_111',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'United Arab Emirates',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ClinicUpdated->value,
            'auditable_id' => $clinic->id,
        ]);
    }
}
