<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationClinicOwnershipFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_lab_admin_crud_still_works_and_assigns_clinic_111(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $clinic = $this->clinic111();

        $this->actingAs($admin)
            ->post(route('labs.store'), [
                'name' => 'Ownership Lab',
                'code' => 'OWN_LAB',
            ])
            ->assertRedirect(route('labs.index'));

        $lab = Lab::query()->where('code', 'OWN_LAB')->firstOrFail();
        $this->assertSame($clinic->id, $lab->clinic_id);
    }

    public function test_lab_admin_api_still_works_after_clinic_attachment(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/admin/labs')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'MAIN_LAB');

        $this->postJson('/api/admin/labs', [
            'name' => 'API Ownership Lab',
            'code' => 'API_OWN_LAB',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'API_OWN_LAB');

        $lab = Lab::query()->where('code', 'API_OWN_LAB')->firstOrFail();
        $this->assertSame($this->clinic111()->id, $lab->clinic_id);
    }

    public function test_reference_api_unchanged_after_clinic_attachment(): void
    {
        $this->actingAsRole('viewer');

        $codes = collect($this->getJson('/api/labs')->json('data'))->pluck('code');

        $this->assertTrue($codes->contains('MAIN_LAB'));
        $this->assertTrue($codes->contains('RIYADH_LAB'));
    }

    public function test_seeded_admin_user_belongs_to_clinic_111(): void
    {
        $clinic = Clinic::query()->where('code', 'CLINIC_111')->firstOrFail();
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->assertSame($clinic->id, $admin->clinic_id);
        $this->assertTrue($admin->clinic->is($clinic));
    }
}
