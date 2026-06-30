<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Accounting\TreatmentParserService;
use App\Services\DailyReport\DoctorTreatmentCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_admin_can_view_treatment_index_with_modal_markup(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('treatments.index', ['page' => 1]))
            ->assertOk()
            ->assertSee('data-open-create', false)
            ->assertSee('tx-create-modal', false)
            ->assertSee('tx-edit-modal', false)
            ->assertSee('tx-edit-btn', false)
            ->assertSee('DOMContentLoaded', false);
    }

    public function test_admin_can_update_treatment_from_ui(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'CF')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('treatments.index', ['page' => 1]))
            ->put(route('treatments.update', $treatment), [
                '_form' => 'edit',
                '_update_url' => route('treatments.update', $treatment),
                'return_page' => '1',
                'code' => 'CF',
                'name' => 'Composite Filling Updated UI',
                'description' => 'Updated from UI test',
                'has_lab_cost' => '0',
                'is_active' => '1',
            ])
            ->assertRedirect(route('treatments.index', ['page' => 1]))
            ->assertSessionHas('success');

        $this->assertSame('Composite Filling Updated UI', $treatment->fresh()->name);
    }

    public function test_viewer_cannot_access_treatment_admin(): void
    {
        $viewer = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('treatments.index'))
            ->assertForbidden();

        $this->actingAsRole('viewer');
        $this->getJson('/api/admin/treatments')->assertForbidden();
    }

    public function test_accountant_cannot_access_treatment_admin(): void
    {
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $this->actingAs($accountant)
            ->get(route('treatments.index'))
            ->assertForbidden();

        $this->actingAsRole('accountant');
        $this->postJson('/api/admin/treatments', [
            'code' => 'NEW',
            'name' => 'New Treatment',
        ])->assertForbidden();
    }

    public function test_admin_can_create_treatment(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('treatments.store'), [
                'code' => 'new-tx',
                'name' => 'New Treatment',
                'description' => 'Optional notes',
                'has_lab_cost' => '1',
            ])
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('treatments', [
            'code' => 'NEW-TX',
            'name' => 'New Treatment',
            'has_lab_cost' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TreatmentCreated->value,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_update_treatment(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'CF')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('treatments.update', $treatment), [
                'code' => 'CF',
                'name' => 'Composite Filling Updated',
                'description' => 'Updated description',
                'has_lab_cost' => '0',
                'is_active' => '1',
            ])
            ->assertRedirect(route('treatments.index'));

        $treatment->refresh();
        $this->assertSame('Composite Filling Updated', $treatment->name);
        $this->assertSame('Updated description', $treatment->description);
    }

    public function test_admin_can_deactivate_treatment_without_deleting_row(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $treatment = $this->createTreatment('TEMP_TX', 'Temp Treatment');

        $this->actingAs($admin)
            ->delete(route('treatments.destroy', $treatment))
            ->assertRedirect(route('treatments.index'));

        $this->assertDatabaseHas('treatments', [
            'id' => $treatment->id,
            'code' => 'TEMP_TX',
            'is_active' => false,
        ]);
    }

    public function test_store_validation_rejects_duplicate_code(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('treatments.index'))
            ->post(route('treatments.store'), [
                'code' => 'MC',
                'name' => 'Duplicate',
            ])
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHasErrors('code');
    }

    public function test_inactive_treatments_are_hidden_from_editor_catalog(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'CF')->firstOrFail();

        $treatment->is_active = false;
        $treatment->save();

        $catalog = app(DoctorTreatmentCatalogService::class)->forDoctor($doctor);
        $codes = $catalog->pluck('code');

        $this->assertFalse($codes->contains('CF'));
    }

    public function test_historical_work_items_remain_after_treatment_deactivation(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = $this->createTreatment('HIST_TX', 'Historical Treatment');
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => 'manual_entry',
            'source_file_name' => 'hist',
            'status' => 'calculated',
        ]);
        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
            'treatment_text' => 'HIST_TX x 1',
            'paid_total_aed' => '100.00',
        ]);
        $this->createWorkItem($workRow, [
            'treatment_id' => $treatment->id,
            'quantity' => 1,
            'confidence' => 100,
        ]);

        $this->actingAs($admin)
            ->delete(route('treatments.destroy', $treatment))
            ->assertRedirect(route('treatments.index'));

        $this->assertDatabaseHas('work_items', [
            'daily_work_row_id' => $workRow->id,
            'treatment_id' => $treatment->id,
        ]);
        $workRow->load('workItems.treatment');
        $this->assertSame('HIST_TX', $workRow->workItems->first()->treatment->code);
    }

    public function test_parser_resolves_active_treatments_from_database(): void
    {
        $this->authenticateAdmin();
        $this->createTreatment('PARSER_TX', 'Parser Treatment');

        $parser = app(TreatmentParserService::class);
        $parsed = $parser->parse('PARSER_TX x 2');

        $this->assertCount(1, $parsed);
        $this->assertSame('PARSER_TX', $parsed[0]->treatmentCode);
        $this->assertSame(2, $parsed[0]->quantity);
    }

    public function test_reference_api_still_returns_only_active_treatments(): void
    {
        $inactive = $this->createTreatment('INACTIVE_REF', 'Inactive Ref');
        $inactive->is_active = false;
        $inactive->save();

        $this->actingAsRole('viewer');

        $codes = collect($this->getJson('/api/treatments')->json('data'))->pluck('code');

        $this->assertTrue($codes->contains('MC'));
        $this->assertFalse($codes->contains('INACTIVE_REF'));
    }

    public function test_deactivation_creates_audit_log(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $treatment = $this->createTreatment('AUDIT_TX', 'Audit Treatment');

        $this->actingAs($admin)
            ->delete(route('treatments.destroy', $treatment))
            ->assertRedirect(route('treatments.index'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TreatmentDeactivated->value,
            'auditable_id' => $treatment->id,
            'user_id' => $admin->id,
        ]);
    }

    private function createTreatment(string $code, string $name, bool $hasLabCost = false): Treatment
    {
        $treatment = Treatment::query()->create($this->withClinicId([
            'code' => $code,
            'name' => $name,
        ]));
        $treatment->has_lab_cost = $hasLabCost;
        $treatment->is_active = true;
        $treatment->save();

        return $treatment->fresh();
    }
}
