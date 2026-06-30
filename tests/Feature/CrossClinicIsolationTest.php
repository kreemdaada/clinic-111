<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossClinicIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $clinic222;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->clinic222 = $this->seedClinic222Tenant();
    }

    public function test_clinic_111_user_cannot_see_clinic_222_doctors_in_web_ui(): void
    {
        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin111)
            ->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('JACK')
            ->assertDontSee('C222_DOC');
    }

    public function test_clinic_222_user_cannot_see_clinic_111_doctors_in_web_ui(): void
    {
        $this->actingAs($this->clinic222['admin'])
            ->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('C222_DOC')
            ->assertDontSee('JACK');
    }

    public function test_clinic_111_user_cannot_see_clinic_222_labs(): void
    {
        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin111)
            ->get(route('labs.index'))
            ->assertOk()
            ->assertSee('MAIN_LAB')
            ->assertDontSee('C222_LAB');
    }

    public function test_clinic_222_user_cannot_see_clinic_111_labs(): void
    {
        $this->actingAs($this->clinic222['admin'])
            ->get(route('labs.index'))
            ->assertOk()
            ->assertSee('>C222_LAB<', false)
            ->assertDontSee('class="lab-admin-code">MAIN_LAB', false);
    }

    public function test_clinic_111_user_cannot_see_clinic_222_treatments(): void
    {
        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin111)
            ->get(route('treatments.index'))
            ->assertOk()
            ->assertSee('BG')
            ->assertDontSee('C222_TX');
    }

    public function test_clinic_222_user_cannot_see_clinic_111_treatments(): void
    {
        $this->actingAs($this->clinic222['admin'])
            ->get(route('treatments.index'))
            ->assertOk()
            ->assertSee('C222_TX')
            ->assertDontSee('BG');
    }

    public function test_clinic_111_user_cannot_see_clinic_222_lab_prices(): void
    {
        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin111)
            ->get(route('lab-prices.index'))
            ->assertOk()
            ->assertDontSee('C222_LAB')
            ->assertDontSee('C222_TX');
    }

    public function test_clinic_222_user_cannot_see_clinic_111_lab_prices(): void
    {
        $this->actingAs($this->clinic222['admin'])
            ->get(route('lab-prices.index'))
            ->assertOk()
            ->assertSee('C222_LAB')
            ->assertDontSee('>MAIN_LAB<');
    }

    public function test_clinic_111_user_cannot_see_clinic_222_users(): void
    {
        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin111)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('admin@clinic.test')
            ->assertDontSee('admin@clinic222.test');
    }

    public function test_clinic_222_user_cannot_see_clinic_111_users(): void
    {
        $this->actingAs($this->clinic222['admin'])
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('admin@clinic222.test')
            ->assertDontSee('admin@clinic.test');
    }

    public function test_reference_api_doctors_are_clinic_scoped(): void
    {
        $this->actingAsRole('admin');
        $response111 = $this->getJson('/api/doctors')->assertOk();
        $codes111 = collect($response111->json('data'))->pluck('code')->all();

        $this->assertContains('JACK', $codes111);
        $this->assertNotContains('C222_DOC', $codes111);

        $this->actingAs($this->clinic222['viewer']);
        $response222 = $this->getJson('/api/doctors')->assertOk();
        $codes222 = collect($response222->json('data'))->pluck('code')->all();

        $this->assertContains('C222_DOC', $codes222);
        $this->assertNotContains('JACK', $codes222);
    }

    public function test_reference_api_labs_and_treatments_are_clinic_scoped(): void
    {
        $this->actingAsRole('admin');

        $labCodes = collect($this->getJson('/api/labs')->json('data'))->pluck('code')->all();
        $treatmentCodes = collect($this->getJson('/api/treatments')->json('data'))->pluck('code')->all();

        $this->assertContains('MAIN_LAB', $labCodes);
        $this->assertNotContains('C222_LAB', $labCodes);
        $this->assertContains('BG', $treatmentCodes);
        $this->assertNotContains('C222_TX', $treatmentCodes);

        $this->actingAs($this->clinic222['viewer']);

        $labCodes222 = collect($this->getJson('/api/labs')->json('data'))->pluck('code')->all();
        $treatmentCodes222 = collect($this->getJson('/api/treatments')->json('data'))->pluck('code')->all();

        $this->assertContains('C222_LAB', $labCodes222);
        $this->assertNotContains('MAIN_LAB', $labCodes222);
        $this->assertContains('C222_TX', $treatmentCodes222);
        $this->assertNotContains('BG', $treatmentCodes222);
    }

    public function test_cross_clinic_update_returns_not_found(): void
    {
        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor222 = $this->clinic222['doctor'];

        $this->actingAs($admin111)
            ->put(route('doctors.update', $doctor222), [
                'name' => 'Hacked Doctor',
                'commission_type' => 'percentage',
                'commission_percentage' => 10,
            ])
            ->assertNotFound();

        $this->assertSame('Dr Clinic 222', $doctor222->fresh()->name);
    }

    public function test_clinic_admin_lists_only_current_clinic(): void
    {
        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin111)
            ->get(route('clinics.index'))
            ->assertOk()
            ->assertSee('class="clinic-admin-code">CLINIC_111', false)
            ->assertDontSee('class="clinic-admin-code">CLINIC_222', false);

        $this->actingAs($this->clinic222['admin'])
            ->get(route('clinics.index'))
            ->assertOk()
            ->assertSee('class="clinic-admin-code">CLINIC_222', false)
            ->assertDontSee('class="clinic-admin-code">CLINIC_111', false);
    }

    public function test_cross_clinic_clinic_update_returns_not_found(): void
    {
        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $clinic222 = $this->clinic222['clinic'];

        $this->actingAs($admin111)
            ->put(route('clinics.update', $clinic222), [
                'name' => 'Hacked Clinic',
                'code' => 'CLINIC_222',
                'currency' => 'AED',
                'timezone' => 'Asia/Dubai',
                'country' => 'United Arab Emirates',
                'is_active' => '1',
            ])
            ->assertNotFound();
    }

    public function test_two_clinics_can_use_the_same_treatment_code(): void
    {
        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($this->clinic222['admin'])
            ->post(route('treatments.store'), [
                'code' => 'ZIR',
                'name' => 'Clinic 222 Zirconia',
                'has_lab_cost' => true,
            ])
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('treatments', [
            'clinic_id' => $this->clinic222['clinic']->id,
            'code' => 'ZIR',
        ]);

        $this->actingAs($admin111)
            ->post(route('treatments.store'), [
                'code' => 'ZIR',
                'name' => 'Duplicate attempt in clinic 111',
                'has_lab_cost' => true,
            ])
            ->assertSessionHasErrors('code');
    }
}
