<?php

namespace Tests\Unit;

use App\Enums\AuditAction;
use App\Models\Lab;
use App\Models\User;
use App\Services\Accounting\LabManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private LabManagementService $labManagementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->labManagementService = app(LabManagementService::class);
    }

    public function test_create_laboratory_uppercases_code_and_logs_audit(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $this->actingAs($admin);

        $lab = $this->labManagementService->create([
            'name' => 'Service Lab',
            'code' => 'svc_lab',
        ]);

        $this->assertSame('SVC_LAB', $lab->code);
        $this->assertTrue($lab->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LabCreated->value,
            'auditable_id' => $lab->id,
        ]);
    }

    public function test_deactivate_sets_is_active_false_without_deleting_row(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $lab = Lab::query()->create([
            'name' => 'Deactivate Service Lab',
            'code' => 'DEACT_SVC',
        ]);
        $this->actingAs($admin);

        $deactivated = $this->labManagementService->deactivate($lab);

        $this->assertFalse($deactivated->is_active);
        $this->assertDatabaseHas('labs', ['id' => $lab->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LabDeactivated->value,
            'auditable_id' => $lab->id,
        ]);
    }

    public function test_activate_sets_is_active_true_and_logs_audit(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $lab = Lab::query()->create([
            'name' => 'Reactivate Service Lab',
            'code' => 'REACT_SVC',
        ]);
        $lab->is_active = false;
        $lab->save();
        $this->actingAs($admin);

        $activated = $this->labManagementService->activate($lab);

        $this->assertTrue($activated->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LabActivated->value,
            'auditable_id' => $lab->id,
        ]);
    }

    public function test_update_name_logs_lab_updated_audit(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $lab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $this->actingAs($admin);

        $this->labManagementService->update($lab, [
            'name' => 'Main Lab Renamed',
            'code' => 'MAIN_LAB',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LabUpdated->value,
            'auditable_id' => $lab->id,
        ]);
    }
}
