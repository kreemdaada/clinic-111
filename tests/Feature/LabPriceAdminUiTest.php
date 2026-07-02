<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UI regression tests for the lab prices admin page (modal edit/create fixes).
 */
class LabPriceAdminUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_index_renders_edit_buttons_with_data_attributes(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $price = LabPrice::query()->where('is_active', true)->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('lab-prices.index'));

        $response->assertOk();
        $response->assertSee('class="btn btn-secondary btn-sm lp-edit-btn"', false);
        $response->assertSee('data-update-url="'.route('lab-prices.update', $price).'"', false);
        $response->assertSee('data-activate-url="'.route('lab-prices.activate', $price).'"', false);
        $response->assertSee('data-duplicate-url="'.route('lab-prices.duplicate', $price).'"', false);
        $response->assertDontSee('data-edit-price="', false);
    }

    public function test_create_button_is_outside_filter_form(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $html = $this->actingAs($admin)
            ->get(route('lab-prices.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<\/form>\s*<div style="margin-bottom:1rem;">\s*<button[^>]+data-open-create/',
            $html,
        );
    }

    public function test_store_preserves_filters_via_return_params(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();
        $treatment = $this->createLabCostTreatment('UI_LP', 'UI Lab Price');

        $response = $this->actingAs($admin)
            ->from(route('lab-prices.index', [
                'lab_id' => $mainLab->id,
                'status' => 'active',
                'doctor_id' => 'general',
            ]))
            ->post(route('lab-prices.store'), [
                '_form' => 'create',
                'return_lab_id' => (string) $mainLab->id,
                'return_status' => 'active',
                'return_doctor_id' => 'general',
                'lab_id' => $mainLab->id,
                'treatment_id' => $treatment->id,
                'unit_cost' => '222.00',
                'currency' => 'AED',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('success');
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('lab_id='.$mainLab->id, $location);
        $this->assertStringContainsString('status=active', $location);
        $this->assertStringContainsString('doctor_id=general', $location);
    }

    public function test_update_preserves_applied_filters(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $price = $this->createPrice('UI_UPD');

        $response = $this->actingAs($admin)
            ->from(route('lab-prices.index', [
                'lab_id' => $price->lab_id,
                'status' => 'active',
                'currency' => 'AED',
            ]))
            ->put(route('lab-prices.update', $price), [
                '_form' => 'edit',
                '_update_url' => route('lab-prices.update', $price),
                'return_lab_id' => (string) $price->lab_id,
                'return_status' => 'active',
                'return_currency' => 'AED',
                'lab_id' => $price->lab_id,
                'treatment_id' => $price->treatment_id,
                'doctor_id' => '',
                'unit_cost' => '175.00',
                'currency' => 'AED',
                'is_active' => '1',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('success');
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('lab_id='.$price->lab_id, $location);
        $this->assertStringContainsString('status=active', $location);
        $this->assertStringContainsString('currency=AED', $location);

        $this->assertSame('175.00', (string) $price->fresh()->unit_cost);
    }

    public function test_update_validation_reopens_edit_modal_on_overlap(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $existing = LabPrice::query()->where('is_active', true)->whereNull('doctor_id')->firstOrFail();
        $duplicate = LabPrice::query()->create($this->withClinicId([
            'lab_id' => $existing->lab_id,
            'treatment_id' => $existing->treatment_id,
            'unit_cost' => '99.00',
            'currency' => 'AED',
        ]));
        $duplicate->is_active = false;
        $duplicate->save();

        $page = $this->actingAs($admin)
            ->from(route('lab-prices.index'))
            ->followingRedirects()
            ->put(route('lab-prices.update', $duplicate), [
                '_form' => 'edit',
                '_update_url' => route('lab-prices.update', $duplicate),
                'lab_id' => $duplicate->lab_id,
                'treatment_id' => $duplicate->treatment_id,
                'doctor_id' => '',
                'unit_cost' => '99.00',
                'currency' => 'AED',
                'is_active' => '1',
            ]);

        $page->assertOk();
        $this->assertMatchesRegularExpression(
            '/id="lp-edit-modal"[\s\S]*?data-open-on-load="1"/',
            $page->getContent(),
        );
    }

    public function test_duplicate_and_activate_via_ui_routes(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $price = $this->createPrice('UI_DUP');

        $this->actingAs($admin)
            ->delete(route('lab-prices.destroy', $price))
            ->assertRedirect(route('lab-prices.index'))
            ->assertSessionHas('success');

        $this->assertFalse($price->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('lab-prices.activate', $price))
            ->assertRedirect(route('lab-prices.index'))
            ->assertSessionHas('success');

        $this->assertTrue($price->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('lab-prices.duplicate', $price))
            ->assertRedirect(route('lab-prices.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lab_prices', [
            'lab_id' => $price->lab_id,
            'treatment_id' => $price->treatment_id,
            'unit_cost' => (string) $price->unit_cost,
            'is_active' => false,
        ]);
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
