<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_viewer_cannot_access_lab_admin(): void
    {
        $viewer = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('labs.index'))
            ->assertForbidden();

        $this->actingAsRole('viewer');
        $this->getJson('/api/admin/labs')->assertForbidden();
    }

    public function test_accountant_cannot_access_lab_admin(): void
    {
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $this->actingAs($accountant)
            ->get(route('labs.index'))
            ->assertForbidden();

        $this->actingAsRole('accountant');
        $this->postJson('/api/admin/labs', [
            'name' => 'Blocked Lab',
            'code' => 'BLOCKED',
        ])->assertForbidden();
    }

    public function test_admin_can_create_laboratory(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('labs.store'), [
                'name' => 'Secondary Lab',
                'code' => 'sec_lab',
            ])
            ->assertRedirect(route('labs.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('labs', [
            'name' => 'Secondary Lab',
            'code' => 'SEC_LAB',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LabCreated->value,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_update_laboratory(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $lab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('labs.update', $lab), [
                'name' => 'Main Laboratory Updated',
                'code' => 'MAIN_LAB',
                'is_active' => '1',
            ])
            ->assertRedirect(route('labs.index'));

        $this->assertSame('Main Laboratory Updated', $lab->fresh()->name);
    }

    public function test_admin_can_deactivate_laboratory_without_deleting_row(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $lab = Lab::query()->create($this->withClinicId([
            'name' => 'Temp Lab',
            'code' => 'TEMP_LAB',
        ]));

        $this->actingAs($admin)
            ->delete(route('labs.destroy', $lab))
            ->assertRedirect(route('labs.index'));

        $this->assertDatabaseHas('labs', [
            'id' => $lab->id,
            'code' => 'TEMP_LAB',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_activate_laboratory(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $lab = Lab::query()->create($this->withClinicId([
            'name' => 'Inactive Lab',
            'code' => 'INACTIVE_LAB',
        ]));
        $lab->is_active = false;
        $lab->save();

        $this->actingAs($admin)
            ->post(route('labs.activate', $lab))
            ->assertRedirect(route('labs.index'));

        $this->assertTrue($lab->fresh()->is_active);
    }

    public function test_search_filters_laboratories_by_name_or_code(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('labs.index', ['search' => 'RIYADH', 'status' => 'all']))
            ->assertOk()
            ->assertSee('RIYADH_LAB')
            ->assertDontSee('class="lab-admin-code">MAIN_LAB</h2>');
    }

    public function test_status_filter_shows_only_inactive_laboratories(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $hiddenLab = Lab::query()->create($this->withClinicId([
            'name' => 'Hidden Lab',
            'code' => 'HIDDEN_LAB',
        ]));
        $hiddenLab->is_active = false;
        $hiddenLab->save();

        $response = $this->actingAs($admin)
            ->get(route('labs.index', ['status' => 'inactive']))
            ->assertOk();

        $response->assertSee('HIDDEN_LAB');
        $response->assertDontSee('class="lab-admin-code">MAIN_LAB</h2>');
    }

    public function test_store_validation_rejects_duplicate_code(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('labs.index'))
            ->post(route('labs.store'), [
                'name' => 'Duplicate',
                'code' => 'MAIN_LAB',
            ])
            ->assertRedirect(route('labs.index'))
            ->assertSessionHasErrors('code');
    }

    public function test_reference_api_still_returns_only_active_labs(): void
    {
        $inactiveLab = Lab::query()->create($this->withClinicId([
            'name' => 'Inactive Reference',
            'code' => 'INACTIVE_REF',
        ]));
        $inactiveLab->is_active = false;
        $inactiveLab->save();

        $this->actingAsRole('viewer');

        $codes = collect($this->getJson('/api/labs')->json('data'))->pluck('code');

        $this->assertTrue($codes->contains('MAIN_LAB'));
        $this->assertFalse($codes->contains('INACTIVE_REF'));
    }

    public function test_deactivation_audit_log_is_created(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $lab = Lab::query()->create($this->withClinicId([
            'name' => 'Audit Lab',
            'code' => 'AUDIT_LAB',
        ]));

        $this->actingAs($admin)
            ->delete(route('labs.destroy', $lab))
            ->assertRedirect(route('labs.index'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LabDeactivated->value,
            'auditable_type' => $lab->getMorphClass(),
            'auditable_id' => $lab->id,
            'user_id' => $admin->id,
        ]);
    }
}
