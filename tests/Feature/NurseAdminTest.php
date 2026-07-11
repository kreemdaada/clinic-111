<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\NurseCommission;
use App\Models\Treatment;
use App\Models\User;
use App\Support\ConfigurationReturnContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NurseAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_admin_can_open_nurse_list(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('nurses.index'))
            ->assertOk()
            ->assertSee(__('nurses.title'))
            ->assertSee(__('nurses.create'));
    }

    public function test_viewer_cannot_access_nurse_admin(): void
    {
        $viewer = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('nurses.index'))
            ->assertForbidden();

        $this->actingAsRole('viewer');
        $this->getJson('/api/admin/nurses')->assertForbidden();
    }

    public function test_accountant_cannot_access_nurse_admin(): void
    {
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $this->actingAs($accountant)
            ->get(route('nurses.index'))
            ->assertForbidden();

        $this->actingAsRole('accountant');
        $this->postJson('/api/admin/nurses', [
            'name' => 'Blocked Nurse',
            'code' => 'BLOCKED',
        ])->assertForbidden();
    }

    public function test_admin_can_create_nurse(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('nurses.store'), [
                'name' => 'Sarah Ahmed',
                'code' => 'nurse_sarah',
            ])
            ->assertRedirect(route('nurses.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('nurses', [
            'name' => 'Sarah Ahmed',
            'code' => 'NURSE_SARAH',
            'is_active' => true,
        ]);
    }

    public function test_create_assigns_current_clinic_id(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('nurses.store'), [
                'name' => 'Clinic Nurse',
                'code' => 'CLINIC_NURSE',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('nurses', [
            'code' => 'CLINIC_NURSE',
            'clinic_id' => $this->clinic111()->id,
        ]);
    }

    public function test_create_rejects_duplicate_code_in_same_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        Nurse::query()->create([
            'clinic_id' => $this->clinic111()->id,
            'code' => 'DUPLICATE',
            'name' => 'Existing Nurse',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('nurses.index'))
            ->post(route('nurses.store'), [
                'name' => 'Another Nurse',
                'code' => 'duplicate',
            ])
            ->assertRedirect(route('nurses.index'))
            ->assertSessionHasErrors('code');
    }

    public function test_same_code_is_allowed_in_different_clinics(): void
    {
        $tenant222 = $this->seedClinic222Tenant();
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        Nurse::query()->create([
            'clinic_id' => $this->clinic111()->id,
            'code' => 'SHARED_NURSE',
            'name' => 'Clinic 111 Nurse',
            'is_active' => true,
        ]);

        $this->actingAs($tenant222['admin'])
            ->post(route('nurses.store'), [
                'name' => 'Clinic 222 Nurse',
                'code' => 'SHARED_NURSE',
            ])
            ->assertRedirect(route('nurses.index'));

        $this->assertDatabaseCount('nurses', 2);
    }

    public function test_admin_can_update_nurse(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $nurse = Nurse::query()->create($this->withClinicId([
            'code' => 'UPDATE_ME',
            'name' => 'Original Name',
        ]));

        $this->actingAs($admin)
            ->put(route('nurses.update', $nurse), [
                'name' => 'Updated Name',
                'code' => 'UPDATED',
            ])
            ->assertRedirect(route('nurses.index'));

        $nurse->refresh();
        $this->assertSame('Updated Name', $nurse->name);
        $this->assertSame('UPDATED', $nurse->code);
        $this->assertTrue($nurse->is_active);
    }

    public function test_admin_can_deactivate_nurse_without_deleting_row(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $nurse = Nurse::query()->create($this->withClinicId([
            'code' => 'DEACT_NURSE',
            'name' => 'Deactivate Me',
        ]));

        $this->actingAs($admin)
            ->delete(route('nurses.destroy', $nurse))
            ->assertRedirect(route('nurses.index'));

        $this->assertDatabaseHas('nurses', [
            'id' => $nurse->id,
            'code' => 'DEACT_NURSE',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_activate_nurse(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $nurse = Nurse::query()->create($this->withClinicId([
            'code' => 'INACTIVE_NURSE',
            'name' => 'Inactive Nurse',
            'is_active' => false,
        ]));

        $this->actingAs($admin)
            ->post(route('nurses.activate', $nurse))
            ->assertRedirect(route('nurses.index'));

        $this->assertTrue($nurse->fresh()->is_active);
    }

    public function test_search_finds_nurse_by_code(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        Nurse::query()->create($this->withClinicId([
            'code' => 'FIND_CODE',
            'name' => 'Findable Nurse',
        ]));
        Nurse::query()->create($this->withClinicId([
            'code' => 'OTHER_CODE',
            'name' => 'Other Nurse',
        ]));

        $this->actingAs($admin)
            ->get(route('nurses.index', ['search' => 'FIND_CODE']))
            ->assertOk()
            ->assertSee('FIND_CODE')
            ->assertDontSee('OTHER_CODE');
    }

    public function test_search_finds_nurse_by_name(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        Nurse::query()->create($this->withClinicId([
            'code' => 'NAME_CODE',
            'name' => 'Unique Nurse Name',
        ]));

        $this->actingAs($admin)
            ->get(route('nurses.index', ['search' => 'Unique Nurse']))
            ->assertOk()
            ->assertSee('Unique Nurse Name')
            ->assertSee('NAME_CODE');
    }

    public function test_status_filter_shows_only_inactive_nurses(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $inactive = Nurse::query()->create($this->withClinicId([
            'code' => 'INACTIVE_ONLY',
            'name' => 'Inactive Only Nurse',
            'is_active' => false,
        ]));
        Nurse::query()->create($this->withClinicId([
            'code' => 'VISIBLE_ACTIVE',
            'name' => 'Visible Active Nurse',
        ]));

        $response = $this->actingAs($admin)
            ->get(route('nurses.index', ['status' => 'inactive']))
            ->assertOk();

        $response->assertSee('INACTIVE_ONLY');
        $response->assertDontSee('VISIBLE_ACTIVE');
        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_search_and_status_filter_work_together(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        Nurse::query()->create($this->withClinicId([
            'code' => 'BOTH_MATCH',
            'name' => 'Filter Target',
            'is_active' => false,
        ]));
        Nurse::query()->create($this->withClinicId([
            'code' => 'ACTIVE_MATCH',
            'name' => 'Filter Target Active',
        ]));

        $this->actingAs($admin)
            ->get(route('nurses.index', ['search' => 'Filter Target', 'status' => 'inactive']))
            ->assertOk()
            ->assertSee('BOTH_MATCH')
            ->assertDontSee('ACTIVE_MATCH');
    }

    public function test_empty_search_shows_full_list(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        Nurse::query()->create($this->withClinicId([
            'code' => 'LIST_A',
            'name' => 'Nurse A',
        ]));
        Nurse::query()->create($this->withClinicId([
            'code' => 'LIST_B',
            'name' => 'Nurse B',
        ]));

        $this->actingAs($admin)
            ->get(route('nurses.index'))
            ->assertOk()
            ->assertSee('LIST_A')
            ->assertSee('LIST_B');
    }

    public function test_pagination_preserves_filters_and_configuration_context(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        for ($i = 1; $i <= 21; $i++) {
            Nurse::query()->create($this->withClinicId([
                'code' => 'PAGE_NURSE_'.$i,
                'name' => 'Paged Nurse '.$i,
            ]));
        }

        $response = $this->actingAs($admin)
            ->get(route('nurses.index', array_merge(ConfigurationReturnContext::query(), [
                'search' => 'Paged',
                'status' => 'active',
                'page' => 2,
            ])));

        $response->assertOk()
            ->assertSee(__('navigation.back_to_configuration'), false)
            ->assertSee('from=configuration', false);
    }

    public function test_configuration_dashboard_links_to_nurses_with_return_context(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('configuration.dashboard'))
            ->assertOk()
            ->assertSee(route('nurses.index', ConfigurationReturnContext::query()), false)
            ->assertSee(__('dashboard.modules.manage_nurses'));
    }

    public function test_nurse_page_shows_back_to_configuration_with_valid_context(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('nurses.index', ConfigurationReturnContext::query()))
            ->assertOk()
            ->assertSee(__('navigation.back_to_configuration'), false)
            ->assertSee(route('configuration.dashboard'), false);
    }

    public function test_manipulated_return_context_does_not_open_redirect(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('nurses.index', ['from' => 'https://evil.example']))
            ->assertOk()
            ->assertDontSee(__('navigation.back_to_configuration'), false);

        $response = $this->actingAs($admin)
            ->from(route('nurses.index', ['from' => 'https://evil.example']))
            ->post(route('nurses.store'), [
                'name' => 'Evil Nurse',
                'code' => 'EVIL',
                'return_from' => 'https://evil.example',
            ]);

        $response->assertRedirect(route('nurses.index'));
        $this->assertStringNotContainsString('evil.example', (string) $response->headers->get('Location'));
    }

    public function test_cross_clinic_update_is_rejected(): void
    {
        $tenant222 = $this->seedClinic222Tenant();
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $foreignNurse = Nurse::query()->create([
            'clinic_id' => $tenant222['clinic']->id,
            'code' => 'FOREIGN_NURSE',
            'name' => 'Foreign Nurse',
        ]);

        $this->actingAs($admin)
            ->put(route('nurses.update', $foreignNurse), [
                'name' => 'Hijacked',
                'code' => 'HIJACKED',
            ])
            ->assertNotFound();
    }

    public function test_cross_clinic_activate_is_rejected(): void
    {
        $tenant222 = $this->seedClinic222Tenant();
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $foreignNurse = Nurse::query()->create([
            'clinic_id' => $tenant222['clinic']->id,
            'code' => 'FOREIGN_ACT',
            'name' => 'Foreign Nurse',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('nurses.activate', $foreignNurse))
            ->assertNotFound();
    }

    public function test_cross_clinic_deactivate_is_rejected(): void
    {
        $tenant222 = $this->seedClinic222Tenant();
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $foreignNurse = Nurse::query()->create([
            'clinic_id' => $tenant222['clinic']->id,
            'code' => 'FOREIGN_DEACT',
            'name' => 'Foreign Nurse',
        ]);

        $this->actingAs($admin)
            ->delete(route('nurses.destroy', $foreignNurse))
            ->assertNotFound();
    }

    public function test_clinic_a_admin_does_not_see_clinic_b_nurses_in_list(): void
    {
        $tenant222 = $this->seedClinic222Tenant();
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        Nurse::query()->create([
            'clinic_id' => $tenant222['clinic']->id,
            'code' => 'SECRET_NURSE',
            'name' => 'Secret Nurse',
        ]);

        $this->actingAs($admin)
            ->get(route('nurses.index'))
            ->assertOk()
            ->assertDontSee('SECRET_NURSE');
    }

    public function test_audit_logs_are_created_for_nurse_lifecycle(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('nurses.store'), [
                'name' => 'Audit Nurse',
                'code' => 'AUDIT_NURSE',
            ]);

        $nurse = Nurse::query()->where('code', 'AUDIT_NURSE')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::NurseCreated->value,
            'auditable_type' => $nurse->getMorphClass(),
            'auditable_id' => $nurse->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('nurses.update', $nurse), [
                'name' => 'Audit Nurse Updated',
                'code' => 'AUDIT_NURSE',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::NurseUpdated->value,
            'auditable_id' => $nurse->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('nurses.destroy', $nurse));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::NurseDeactivated->value,
            'auditable_id' => $nurse->id,
        ]);

        $this->actingAs($admin)
            ->post(route('nurses.activate', $nurse));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::NurseActivated->value,
            'auditable_id' => $nurse->id,
        ]);
    }

    public function test_deactivation_does_not_change_historical_nurse_commission_snapshot(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'MC')->firstOrFail();
        $nurse = Nurse::query()->create($this->withClinicId([
            'code' => 'SNAPSHOT_NURSE',
            'name' => 'Snapshot Nurse',
        ]));

        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
        ]);
        $workItem = $this->createWorkItem($workRow, ['treatment_id' => $treatment->id]);

        $commission = NurseCommission::factory()->forWorkItem($workItem, $nurse)->create([
            'nurse_name_snapshot' => 'Snapshot Nurse',
            'total_commission_aed' => '25.00',
        ]);

        $this->actingAs($admin)
            ->delete(route('nurses.destroy', $nurse))
            ->assertRedirect();

        $commission->refresh();
        $this->assertSame('Snapshot Nurse', $commission->nurse_name_snapshot);
        $this->assertSame('25.00', $commission->total_commission_aed);
        $this->assertSame($nurse->id, $commission->nurse_id);
    }

    public function test_api_admin_can_list_and_manage_nurses(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/admin/nurses', [
            'name' => 'API Nurse',
            'code' => 'API_NURSE',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'API_NURSE');

        $nurse = Nurse::query()->where('code', 'API_NURSE')->firstOrFail();

        $this->getJson('/api/admin/nurses')
            ->assertOk()
            ->assertJsonFragment(['code' => 'API_NURSE']);

        $this->putJson("/api/admin/nurses/{$nurse->id}", [
            'name' => 'API Nurse Updated',
            'code' => 'API_NURSE',
        ])->assertOk();

        $this->deleteJson("/api/admin/nurses/{$nurse->id}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->postJson("/api/admin/nurses/{$nurse->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }
}
