<?php

namespace Tests\Unit;

use App\Enums\AuditAction;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Accounting\TreatmentManagementService;
use App\Support\LabCostTreatmentCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private TreatmentManagementService $treatmentManagementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->treatmentManagementService = app(TreatmentManagementService::class);
    }

    public function test_create_treatment_uppercases_code_and_logs_audit(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $this->actingAs($admin);

        $treatment = $this->treatmentManagementService->create([
            'code' => 'svc_tx',
            'name' => 'Service Treatment',
            'description' => 'Notes',
            'has_lab_cost' => true,
        ]);

        $this->assertSame('SVC_TX', $treatment->code);
        $this->assertTrue($treatment->has_lab_cost);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TreatmentCreated->value,
            'auditable_id' => $treatment->id,
        ]);
    }

    public function test_deactivate_sets_is_active_false_without_deleting_row(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $treatment = Treatment::query()->create($this->withClinicId(['code' => 'DEACT_SVC', 'name' => 'Deactivate']));
        $treatment->is_active = true;
        $treatment->save();
        $this->actingAs($admin);

        $deactivated = $this->treatmentManagementService->deactivate($treatment);

        $this->assertFalse($deactivated->is_active);
        $this->assertDatabaseHas('treatments', ['id' => $treatment->id]);
    }

    public function test_lab_cost_catalog_reads_from_database(): void
    {
        $this->assertTrue(LabCostTreatmentCatalog::isLabCostCode('MC'));
        $this->assertFalse(LabCostTreatmentCatalog::isLabCostCode('CF'));
        $this->assertContains('MC', LabCostTreatmentCatalog::codes());
        $this->assertNotContains('CF', LabCostTreatmentCatalog::codes());
    }

    public function test_nurse_commission_required_clears_external_lab_cost(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $this->actingAs($admin);

        $treatment = $this->treatmentManagementService->create([
            'code' => 'NURSE_ONLY',
            'name' => 'Nurse Only',
            'has_lab_cost' => true,
            'requires_nurse_commission' => true,
            'treatment_price' => '150.00',
            'treatment_price_currency' => 'AED',
        ]);

        $this->assertTrue($treatment->requires_nurse_commission);
        $this->assertFalse($treatment->has_lab_cost);
    }

    public function test_changing_has_lab_cost_updates_catalog_resolution(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $treatment = Treatment::query()->create($this->withClinicId(['code' => 'TOGGLE_TX', 'name' => 'Toggle']));
        $treatment->has_lab_cost = false;
        $treatment->is_active = true;
        $treatment->save();
        $this->actingAs($admin);

        $this->assertFalse(LabCostTreatmentCatalog::isLabCostCode('TOGGLE_TX'));

        $this->treatmentManagementService->update($treatment, [
            'code' => 'TOGGLE_TX',
            'name' => 'Toggle',
            'has_lab_cost' => true,
            'is_active' => true,
        ]);

        $this->assertTrue(LabCostTreatmentCatalog::isLabCostCode('TOGGLE_TX'));
    }
}
