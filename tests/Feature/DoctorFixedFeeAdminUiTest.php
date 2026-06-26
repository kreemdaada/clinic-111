<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UI regression tests for the doctor fixed fees admin page.
 */
class DoctorFixedFeeAdminUiTest extends TestCase
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
        $fee = DoctorFixedFee::query()->where('is_active', true)->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('doctor-fixed-fees.index'));

        $response->assertOk();
        $response->assertSee('class="btn btn-secondary btn-sm dff-edit-btn"', false);
        $response->assertSee('data-update-url="'.route('doctor-fixed-fees.update', $fee).'"', false);
        $response->assertSee('data-activate-url="'.route('doctor-fixed-fees.activate', $fee).'"', false);
        $response->assertSee('data-duplicate-url="'.route('doctor-fixed-fees.duplicate', $fee).'"', false);
    }

    public function test_create_button_is_outside_filter_form(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $html = $this->actingAs($admin)
            ->get(route('doctor-fixed-fees.index'))
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
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $treatment = $this->createTreatment('UI_DFF', 'UI DFF');

        $response = $this->actingAs($admin)
            ->from(route('doctor-fixed-fees.index', [
                'doctor_id' => $doctor->id,
                'status' => 'active',
                'currency' => 'AED',
            ]))
            ->post(route('doctor-fixed-fees.store'), [
                '_form' => 'create',
                'return_doctor_id' => (string) $doctor->id,
                'return_status' => 'active',
                'return_currency' => 'AED',
                'doctor_id' => $doctor->id,
                'treatment_id' => $treatment->id,
                'fee_amount' => '222.00',
                'currency' => 'AED',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('success');
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('doctor_id='.$doctor->id, $location);
        $this->assertStringContainsString('status=active', $location);
        $this->assertStringContainsString('currency=AED', $location);
    }

    public function test_update_validation_reopens_edit_modal(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $fee = DoctorFixedFee::query()->where('is_active', true)->firstOrFail();

        $page = $this->actingAs($admin)
            ->from(route('doctor-fixed-fees.index'))
            ->followingRedirects()
            ->put(route('doctor-fixed-fees.update', $fee), [
                '_form' => 'edit',
                '_update_url' => route('doctor-fixed-fees.update', $fee),
                'doctor_id' => $fee->doctor_id,
                'treatment_id' => $fee->treatment_id,
                'fee_amount' => '',
                'currency' => 'AED',
                'is_active' => '1',
            ]);

        $page->assertOk();
        $this->assertMatchesRegularExpression(
            '/id="dff-edit-modal"[\s\S]*?data-open-on-load="1"/',
            $page->getContent(),
        );
    }

    public function test_duplicate_and_activate_via_ui_routes(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $fee = $this->createFee('UI_DUP');

        $this->actingAs($admin)
            ->delete(route('doctor-fixed-fees.destroy', $fee))
            ->assertRedirect(route('doctor-fixed-fees.index'))
            ->assertSessionHas('success');

        $this->assertFalse($fee->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('doctor-fixed-fees.activate', $fee))
            ->assertRedirect(route('doctor-fixed-fees.index'))
            ->assertSessionHas('success');

        $this->assertTrue($fee->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('doctor-fixed-fees.duplicate', $fee))
            ->assertRedirect(route('doctor-fixed-fees.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('doctor_fixed_fees', [
            'doctor_id' => $fee->doctor_id,
            'treatment_id' => $fee->treatment_id,
            'fee_amount' => (string) $fee->fee_amount,
            'is_active' => false,
        ]);
    }

    private function createTreatment(string $code, string $name): Treatment
    {
        $treatment = Treatment::query()->create([
            'code' => $code,
            'name' => $name,
        ]);
        $treatment->has_lab_cost = false;
        $treatment->is_active = true;
        $treatment->save();

        return $treatment->fresh();
    }

    private function createFee(string $treatmentCode): DoctorFixedFee
    {
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $treatment = $this->createTreatment($treatmentCode, $treatmentCode);

        $fee = DoctorFixedFee::query()->create([
            'doctor_id' => $doctor->id,
            'treatment_id' => $treatment->id,
            'fee_amount' => '150.00',
            'currency' => 'AED',
            'valid_from' => '2035-01-01',
            'valid_to' => '2035-12-31',
        ]);
        $fee->is_active = true;
        $fee->save();

        return $fee->fresh();
    }
}
