<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_seed_creates_exactly_one_default_clinic(): void
    {
        $this->assertSame(1, Clinic::query()->count());
        $this->assertDatabaseHas('clinics', [
            'code' => 'CLINIC_111',
            'name' => 'Clinic 111',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'United Arab Emirates',
            'is_active' => true,
        ]);
    }

    public function test_viewer_cannot_access_clinic_admin(): void
    {
        $viewer = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('clinics.index'))
            ->assertForbidden();

        $this->actingAsRole('viewer');
        $this->getJson('/api/admin/clinics')->assertForbidden();
    }

    public function test_accountant_cannot_access_clinic_admin(): void
    {
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $this->actingAs($accountant)
            ->get(route('clinics.index'))
            ->assertForbidden();

        $this->actingAsRole('accountant');
        $this->postJson('/api/admin/clinics', [
            'name' => 'Blocked Clinic',
            'code' => 'BLOCKED',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'United Arab Emirates',
        ])->assertForbidden();
    }

    public function test_admin_can_create_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('clinics.store'), [
                'name' => 'Second Clinic',
                'code' => 'clinic_222',
                'currency' => 'usd',
                'timezone' => 'America/New_York',
                'country' => 'United States',
            ])
            ->assertRedirect(route('clinics.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clinics', [
            'name' => 'Second Clinic',
            'code' => 'CLINIC_222',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'country' => 'United States',
            'is_active' => true,
        ]);

        $clinic = Clinic::query()->where('code', 'CLINIC_222')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ClinicCreated->value,
            'user_id' => $admin->id,
            'auditable_id' => $clinic->id,
        ]);
    }

    public function test_admin_can_update_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $clinic = Clinic::query()->where('code', 'CLINIC_111')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('clinics.update', $clinic), [
                'name' => 'Clinic 111 Updated',
                'code' => 'CLINIC_111',
                'currency' => 'AED',
                'timezone' => 'Asia/Dubai',
                'country' => 'United Arab Emirates',
                'is_active' => '1',
            ])
            ->assertRedirect(route('clinics.index'));

        $this->assertSame('Clinic 111 Updated', $clinic->fresh()->name);
    }

    public function test_admin_can_deactivate_own_clinic_without_deleting_row(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $clinic = Clinic::query()->where('code', 'CLINIC_111')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('clinics.destroy', $clinic))
            ->assertRedirect(route('clinics.index'));

        $this->assertDatabaseHas('clinics', [
            'id' => $clinic->id,
            'code' => 'CLINIC_111',
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_deactivate_other_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $otherClinic = Clinic::query()->create([
            'name' => 'Temp Clinic',
            'code' => 'TEMP_CLINIC',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'United Arab Emirates',
        ]);

        $this->actingAs($admin)
            ->delete(route('clinics.destroy', $otherClinic))
            ->assertNotFound();
    }

    public function test_admin_can_activate_own_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $clinic = Clinic::query()->where('code', 'CLINIC_111')->firstOrFail();
        $clinic->is_active = false;
        $clinic->save();

        $this->actingAs($admin)
            ->post(route('clinics.activate', $clinic))
            ->assertRedirect(route('clinics.index'));

        $this->assertTrue($clinic->fresh()->is_active);
    }

    public function test_search_filters_clinics_by_name_code_or_country(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('clinics.index', ['search' => 'United Arab', 'status' => 'all']))
            ->assertOk()
            ->assertSee('CLINIC_111');
    }

    public function test_status_filter_shows_only_inactive_current_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $clinic = Clinic::query()->where('code', 'CLINIC_111')->firstOrFail();
        $clinic->is_active = false;
        $clinic->save();

        Clinic::query()->create([
            'name' => 'Hidden Clinic',
            'code' => 'HIDDEN_CLINIC',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'Test Country',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('clinics.index', ['status' => 'inactive']))
            ->assertOk();

        $response->assertSee('CLINIC_111');
        $response->assertDontSee('HIDDEN_CLINIC');
    }

    public function test_store_validation_rejects_duplicate_code(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('clinics.index'))
            ->post(route('clinics.store'), [
                'name' => 'Duplicate',
                'code' => 'CLINIC_111',
                'currency' => 'AED',
                'timezone' => 'Asia/Dubai',
                'country' => 'United Arab Emirates',
            ])
            ->assertRedirect(route('clinics.index'))
            ->assertSessionHasErrors('code');
    }

    public function test_store_validation_rejects_invalid_timezone(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('clinics.index'))
            ->post(route('clinics.store'), [
                'name' => 'Bad Timezone',
                'code' => 'BAD_TZ',
                'currency' => 'AED',
                'timezone' => 'Not/A/Timezone',
                'country' => 'United Arab Emirates',
            ])
            ->assertRedirect(route('clinics.index'))
            ->assertSessionHasErrors('timezone');
    }

    public function test_api_admin_can_list_clinics_with_pagination_meta(): void
    {
        $this->actingAsRole('admin');

        $response = $this->getJson('/api/admin/clinics')->assertOk();

        $response->assertJsonStructure([
            'data' => [
                ['id', 'name', 'code', 'currency', 'timezone', 'country', 'is_active'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

        $this->assertSame('CLINIC_111', $response->json('data.0.code'));
    }

    public function test_deactivation_audit_log_is_created_for_own_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $clinic = Clinic::query()->where('code', 'CLINIC_111')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('clinics.destroy', $clinic))
            ->assertRedirect(route('clinics.index'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ClinicDeactivated->value,
            'auditable_type' => $clinic->getMorphClass(),
            'auditable_id' => $clinic->id,
            'user_id' => $admin->id,
        ]);
    }
}
