<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabJob;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\Accounting\LabPriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabPriceAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_admin_can_view_lab_price_index_with_modal_markup(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('lab-prices.index'))
            ->assertOk()
            ->assertSee('data-open-create', false)
            ->assertSee('lp-create-modal', false)
            ->assertSee('lp-edit-modal', false)
            ->assertSee('lp-edit-btn', false)
            ->assertSee('DOMContentLoaded', false);
    }

    public function test_viewer_cannot_access_lab_price_admin(): void
    {
        $viewer = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('lab-prices.index'))
            ->assertForbidden();

        $this->actingAsRole('viewer');
        $this->getJson('/api/admin/lab-prices')->assertForbidden();
    }

    public function test_accountant_cannot_access_lab_price_admin(): void
    {
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $labPrice = LabPrice::query()->firstOrFail();

        $this->actingAs($accountant)
            ->get(route('lab-prices.index'))
            ->assertForbidden();

        $this->actingAsRole('accountant');
        $this->postJson('/api/admin/lab-prices', [
            'lab_id' => $labPrice->lab_id,
            'treatment_id' => $labPrice->treatment_id,
            'unit_cost' => 99,
            'currency' => 'AED',
        ])->assertForbidden();
    }

    public function test_admin_can_create_general_lab_price(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $treatment = $this->createLabCostTreatment('LP_NEW', 'LP New Treatment');

        $this->actingAs($admin)
            ->post(route('lab-prices.store'), [
                'lab_id' => $mainLab->id,
                'treatment_id' => $treatment->id,
                'unit_cost' => '250.50',
                'currency' => 'aed',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lab_prices', [
            'lab_id' => $mainLab->id,
            'treatment_id' => $treatment->id,
            'doctor_id' => null,
            'unit_cost' => '250.50',
            'currency' => 'AED',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LabPriceCreated->value,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_create_doctor_override_price(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $riyadhLab = Lab::query()->where('code', 'RIYADH_LAB')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $treatment = $this->createLabCostTreatment('OVERRIDE_LP', 'Override LP');

        $this->actingAs($admin)
            ->post(route('lab-prices.store'), [
                'lab_id' => $riyadhLab->id,
                'treatment_id' => $treatment->id,
                'doctor_id' => $doctor->id,
                'unit_cost' => '410.00',
                'currency' => 'AED',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lab_prices', [
            'lab_id' => $riyadhLab->id,
            'treatment_id' => $treatment->id,
            'doctor_id' => $doctor->id,
            'unit_cost' => '410.00',
            'is_active' => true,
        ]);
    }

    public function test_store_rejects_duplicate_active_price(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $existing = LabPrice::query()
            ->whereNull('doctor_id')
            ->where('is_active', true)
            ->firstOrFail();

        $this->actingAs($admin)
            ->from(route('lab-prices.index'))
            ->post(route('lab-prices.store'), [
                'lab_id' => $existing->lab_id,
                'treatment_id' => $existing->treatment_id,
                'unit_cost' => '999.00',
                'currency' => 'AED',
            ])
            ->assertRedirect(route('lab-prices.index'))
            ->assertSessionHasErrors('lab_id');
    }

    public function test_admin_can_deactivate_without_deleting_row(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $price = $this->createPrice('DUP_DEACT');

        $this->actingAs($admin)
            ->delete(route('lab-prices.destroy', $price))
            ->assertRedirect(route('lab-prices.index'));

        $this->assertDatabaseHas('lab_prices', [
            'id' => $price->id,
            'is_active' => false,
        ]);
    }

    public function test_duplicate_creates_inactive_copy(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $price = LabPrice::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('lab-prices.duplicate', $price))
            ->assertRedirect(route('lab-prices.index'));

        $this->assertDatabaseHas('lab_prices', [
            'lab_id' => $price->lab_id,
            'treatment_id' => $price->treatment_id,
            'doctor_id' => $price->doctor_id,
            'unit_cost' => (string) $price->unit_cost,
            'is_active' => false,
        ]);
    }

    public function test_lab_price_resolver_uses_doctor_override_and_general_fallback(): void
    {
        $doctorRiyad = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $doctorJack = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'ZIR')->firstOrFail();
        $riyadhLab = Lab::query()->where('code', 'RIYADH_LAB')->firstOrFail();
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $resolver = app(LabPriceResolver::class);

        $riyadPrice = $resolver->resolve($doctorRiyad, $treatment, $riyadhLab);
        $jackPrice = $resolver->resolve($doctorJack, $treatment, $mainLab);

        $this->assertNotNull($riyadPrice);
        $this->assertSame('400.00', number_format((float) $riyadPrice->unit_cost, 2, '.', ''));
        $this->assertNotNull($jackPrice);
        $this->assertSame('360.00', number_format((float) $jackPrice->unit_cost, 2, '.', ''));
    }

    public function test_inactive_price_is_not_resolved_but_historical_lab_jobs_remain(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $price = $this->createPrice('HIST_LP');
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->findOrFail($price->treatment_id);
        $lab = Lab::query()->findOrFail($price->lab_id);
        $report = DailyReport::query()->create([
            'report_date' => '2026-06-01',
            'source_type' => 'manual_entry',
            'source_file_name' => 'hist-lp',
            'status' => 'calculated',
        ]);
        $workRow = DailyWorkRow::query()->create([
            'daily_report_id' => $report->id,
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
            'treatment_text' => $treatment->code.' x 1',
            'paid_total_aed' => '100.00',
        ]);
        $workItem = WorkItem::query()->create([
            'daily_work_row_id' => $workRow->id,
            'treatment_id' => $treatment->id,
            'quantity' => 1,
            'confidence' => 100,
        ]);
        $job = LabJob::query()->create([
            'work_item_id' => $workItem->id,
            'lab_id' => $lab->id,
            'lab_price_id' => $price->id,
            'quantity' => 1,
            'unit_cost' => $price->unit_cost,
            'total_cost_aed' => $price->unit_cost,
        ]);

        $this->actingAs($admin)
            ->delete(route('lab-prices.destroy', $price))
            ->assertRedirect(route('lab-prices.index'));

        $resolver = app(LabPriceResolver::class);
        $this->assertNull($resolver->resolve($doctor, $treatment, $lab));

        $this->assertDatabaseHas('lab_jobs', [
            'id' => $job->id,
            'lab_price_id' => $price->id,
        ]);
    }

    public function test_future_valid_from_price_is_not_resolved_yet(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = $this->createLabCostTreatment('FUTURE_LP', 'Future LP');

        $this->actingAs($admin)
            ->post(route('lab-prices.store'), [
                'lab_id' => $mainLab->id,
                'treatment_id' => $treatment->id,
                'unit_cost' => '111.00',
                'currency' => 'AED',
                'valid_from' => '2099-01-01',
            ])
            ->assertRedirect();

        $resolver = app(LabPriceResolver::class);
        $this->assertNull($resolver->resolve($doctor, $treatment, $mainLab));
    }

    public function test_admin_can_update_lab_price_from_ui(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $price = LabPrice::query()->where('is_active', true)->whereNull('doctor_id')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('lab-prices.index', ['doctor_id' => 'all']))
            ->put(route('lab-prices.update', $price), [
                '_form' => 'edit',
                '_update_url' => route('lab-prices.update', $price),
                'return_doctor_id' => 'all',
                'lab_id' => $price->lab_id,
                'treatment_id' => $price->treatment_id,
                'doctor_id' => '',
                'unit_cost' => '150.00',
                'currency' => 'AED',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('150.00', (string) $price->fresh()->unit_cost);
    }

    public function test_api_index_returns_filtered_prices(): void
    {
        $this->actingAsRole('admin');

        $response = $this->getJson('/api/admin/lab-prices?status=active');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertTrue(collect($response->json('data'))->every(fn (array $row) => $row['is_active'] === true));
    }

    private function createLabCostTreatment(string $code, string $name): Treatment
    {
        $treatment = Treatment::query()->create($this->withClinicId([
            'code' => $code,
            'name' => $name,
        ]));
        $treatment->has_lab_cost = true;
        $treatment->is_active = true;
        $treatment->save();

        return $treatment->fresh();
    }

    private function createPrice(string $treatmentCode): LabPrice
    {
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $treatment = $this->createLabCostTreatment($treatmentCode, $treatmentCode);

        $price = LabPrice::query()->create($this->withClinicId([
            'lab_id' => $mainLab->id,
            'treatment_id' => $treatment->id,
            'unit_cost' => '150.00',
            'currency' => 'AED',
        ]));
        $price->is_active = true;
        $price->save();

        return $price->fresh();
    }
}
