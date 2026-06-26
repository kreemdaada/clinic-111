<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\DailyReport;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabJob;
use App\Models\Payment;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ClinicOnboardingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'clinic_name' => 'Sunrise Dental',
            'clinic_code' => 'SUNRISE',
            'country' => 'United Arab Emirates',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'owner_name' => 'Dr Owner',
            'owner_email' => 'owner@sunrise.test',
            'owner_password' => 'password123',
            'owner_password_confirmation' => 'password123',
        ], $overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_onboarding_creates_clinic(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload())
            ->assertRedirect(route('configuration.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clinics', [
            'code' => 'SUNRISE',
            'name' => 'Sunrise Dental',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'United Arab Emirates',
            'is_active' => true,
        ]);
    }

    public function test_onboarding_creates_owner_admin_user(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload());

        $this->assertDatabaseHas('users', [
            'email' => 'owner@sunrise.test',
            'name' => 'Dr Owner',
            'role' => UserRole::Admin->value,
            'is_active' => true,
        ]);
    }

    public function test_owner_belongs_to_new_clinic(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload());

        $clinic = Clinic::query()->where('code', 'SUNRISE')->firstOrFail();
        $owner = User::query()->where('email', 'owner@sunrise.test')->firstOrFail();

        $this->assertSame($clinic->id, $owner->clinic_id);
    }

    public function test_owner_can_access_configuration_dashboard_after_onboarding(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload());

        $owner = User::query()->where('email', 'owner@sunrise.test')->firstOrFail();

        $this->assertTrue(Auth::check());
        $this->assertSame($owner->id, Auth::id());

        $this->actingAs($owner)
            ->get(route('configuration.dashboard'))
            ->assertOk();
    }

    public function test_onboarding_does_not_create_accounting_records(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload());

        $clinic = Clinic::query()->where('code', 'SUNRISE')->firstOrFail();

        $this->assertSame(0, DailyReport::query()->where('clinic_id', $clinic->id)->count());
        $this->assertSame(0, Payment::query()->where('clinic_id', $clinic->id)->count());
        $this->assertSame(0, WorkItem::query()->where('clinic_id', $clinic->id)->count());
        $this->assertSame(0, LabJob::query()->where('clinic_id', $clinic->id)->count());
    }

    public function test_onboarding_does_not_create_doctors_or_treatments(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload());

        $clinic = Clinic::query()->where('code', 'SUNRISE')->firstOrFail();

        $this->assertSame(0, Doctor::query()->where('clinic_id', $clinic->id)->count());
        $this->assertSame(0, Treatment::query()->where('clinic_id', $clinic->id)->count());
    }

    public function test_onboarding_creates_only_default_lab(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload());

        $clinic = Clinic::query()->where('code', 'SUNRISE')->firstOrFail();

        $this->assertSame(1, Lab::query()->where('clinic_id', $clinic->id)->count());
        $this->assertDatabaseHas('labs', [
            'clinic_id' => $clinic->id,
            'code' => 'SUNRISE_MAIN_LAB',
            'name' => 'Main Laboratory',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_clinic_code_is_rejected(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload([
            'clinic_code' => 'CLINIC_111',
            'owner_email' => 'first@sunrise.test',
        ]))->assertSessionHasErrors('clinic_code');

        $this->assertDatabaseMissing('users', ['email' => 'first@sunrise.test']);
    }

    public function test_duplicate_owner_email_is_rejected(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload([
            'owner_email' => 'admin@clinic.test',
        ]))->assertSessionHasErrors('owner_email');

        $this->assertDatabaseMissing('clinics', ['code' => 'SUNRISE']);
    }

    public function test_onboarding_rolls_back_when_service_fails(): void
    {
        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('logClinicCreated')->once();
            $mock->shouldReceive('logUserCreated')->once()->andThrow(new \RuntimeException('Simulated failure'));
        });

        $this->post(route('register-clinic.store'), $this->validPayload([
            'clinic_code' => 'ROLLBACK_WEB',
            'owner_email' => 'owner@rollback-web.test',
        ]))->assertStatus(500);

        $this->assertDatabaseMissing('clinics', ['code' => 'ROLLBACK_WEB']);
        $this->assertDatabaseMissing('users', ['email' => 'owner@rollback-web.test']);
        $this->assertFalse(Auth::check());
    }

    public function test_new_clinic_cannot_see_clinic_111_data(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload());

        $owner = User::query()->where('email', 'owner@sunrise.test')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('labs.index'))
            ->assertOk()
            ->assertSee('SUNRISE_MAIN_LAB')
            ->assertDontSee('class="lab-admin-code">MAIN_LAB', false);
    }

    public function test_clinic_111_cannot_see_new_clinic_data(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload());

        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin111)
            ->get(route('labs.index'))
            ->assertOk()
            ->assertSee('class="lab-admin-code">MAIN_LAB', false)
            ->assertDontSee('SUNRISE_MAIN_LAB');
    }

    public function test_api_register_clinic_returns_token(): void
    {
        $response = $this->postJson('/api/register-clinic', $this->validPayload([
            'clinic_code' => 'API_CLINIC',
            'owner_email' => 'owner@apiclinic.test',
        ]));

        $response->assertCreated()
            ->assertJsonStructure([
                'token',
                'clinic' => ['id', 'name', 'code', 'currency', 'timezone', 'country'],
                'user' => ['id', 'name', 'email', 'role'],
            ])
            ->assertJsonPath('clinic.code', 'API_CLINIC')
            ->assertJsonPath('user.role', UserRole::Admin->value);

        $this->assertDatabaseHas('clinics', ['code' => 'API_CLINIC']);
    }

    public function test_guest_can_view_onboarding_form(): void
    {
        $this->get(route('register-clinic.create'))
            ->assertOk()
            ->assertSee('Register Your Clinic')
            ->assertSee('AED — UAE Dirham')
            ->assertSee('Asia/Dubai (UAE)')
            ->assertSee('America/New_York (US Eastern)');
    }

    public function test_onboarding_accepts_selected_currency_and_timezone(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload([
            'clinic_code' => 'EURO_CLINIC',
            'currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
            'owner_email' => 'owner@euro.test',
        ]))->assertRedirect(route('configuration.dashboard'));

        $this->assertDatabaseHas('clinics', [
            'code' => 'EURO_CLINIC',
            'currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
        ]);
    }

    public function test_onboarding_rejects_invalid_currency(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload([
            'currency' => 'XYZ',
            'owner_email' => 'owner@invalid-currency.test',
        ]))->assertSessionHasErrors('currency');
    }

    public function test_onboarding_rejects_invalid_timezone(): void
    {
        $this->post(route('register-clinic.store'), $this->validPayload([
            'timezone' => 'Not/A/Timezone',
            'owner_email' => 'owner@invalid-timezone.test',
        ]))->assertSessionHasErrors('timezone');
    }

    public function test_authenticated_user_is_redirected_from_onboarding_form(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('register-clinic.create'))
            ->assertRedirect(route('configuration.dashboard'));
    }
}
