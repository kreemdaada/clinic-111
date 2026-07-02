<?php

namespace Tests\Feature;

use App\Enums\CommissionType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Configuration\BusinessConfigurationService;
use App\Services\Configuration\ConfigurationProgressService;
use App\Support\SecurePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_new_clinic_sees_configuration_wizard_on_dashboard(): void
    {
        $owner = $this->registerClinicOwner('WIZARD_CLINIC', 'owner@wizard.test');

        $this->actingAs($owner)
            ->get(route('configuration.dashboard'))
            ->assertOk()
            ->assertSee('Business Configuration', false)
            ->assertSee('Ready for Import', false)
            ->assertSee('No', false)
            ->assertSee('Continue setup', false)
            ->assertSee('Doctors', false);
    }

    public function test_empty_clinic_cannot_import_from_web_ui(): void
    {
        $owner = $this->registerClinicOwner('EMPTY_CLINIC', 'owner@empty.test');

        $this->actingAs($owner)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee(BusinessConfigurationService::INCOMPLETE_MESSAGE, false)
            ->assertSee('Continue configuration', false);

        $file = UploadedFile::fake()->create('daily-report.xlsx', 128, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($owner)
            ->post(route('imports.store'), ['file' => $file])
            ->assertSessionHasErrors('file');

        $errors = session('errors')->get('file');
        $this->assertContains(BusinessConfigurationService::INCOMPLETE_MESSAGE, $errors);
    }

    public function test_dashboard_shows_correct_progress_after_partial_setup(): void
    {
        $owner = $this->registerClinicOwner('PARTIAL_CLINIC', 'owner@partial.test');
        $clinic = Clinic::query()->where('code', 'PARTIAL_CLINIC')->firstOrFail();
        $lab = Lab::query()->where('clinic_id', $clinic->id)->firstOrFail();

        Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr Partial',
            'code' => 'PART_DOC',
            'commission_type' => CommissionType::Percentage,
            'commission_percentage' => 30,
            'default_lab_id' => $lab->id,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('configuration.dashboard'))
            ->assertOk()
            ->assertSee('75%', false)
            ->assertSee('Lab Prices', false)
            ->assertSee('Next step', false);
    }

    public function test_complete_configuration_enables_import(): void
    {
        $owner = $this->registerClinicOwner('COMPLETE_CLINIC', 'owner@complete.test');
        $clinic = Clinic::query()->where('code', 'COMPLETE_CLINIC')->firstOrFail();
        $lab = Lab::query()->where('clinic_id', $clinic->id)->firstOrFail();

        $this->seedMinimumBusinessConfiguration($clinic, $lab, CommissionType::Percentage);

        $this->actingAs($owner)
            ->get(route('configuration.dashboard'))
            ->assertOk()
            ->assertSee('Configuration complete', false)
            ->assertSee('ready for import', false)
            ->assertSee('Import report', false)
            ->assertDontSee('Business Configuration', false);

        $this->actingAs($owner)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertDontSee(BusinessConfigurationService::INCOMPLETE_MESSAGE, false);
    }

    public function test_percentage_only_clinic_completes_without_fixed_fees(): void
    {
        $owner = $this->registerClinicOwner('PCT_ONLY', 'owner@pctonly.test');
        $clinic = Clinic::query()->where('code', 'PCT_ONLY')->firstOrFail();
        $lab = Lab::query()->where('clinic_id', $clinic->id)->firstOrFail();

        $this->seedMinimumBusinessConfiguration($clinic, $lab, CommissionType::Percentage);

        $this->actingAs($owner);

        $status = app(BusinessConfigurationService::class)->status();

        $this->assertTrue($status['ready_for_import']);
        $fixedFeesStep = collect($status['steps'])
            ->firstWhere('key', ConfigurationProgressService::STEP_DOCTOR_FIXED_FEES);
        $this->assertFalse($fixedFeesStep['required']);
    }

    public function test_fixed_fee_doctors_block_import_until_fees_exist(): void
    {
        $owner = $this->registerClinicOwner('FIXED_ONLY', 'owner@fixedonly.test');
        $clinic = Clinic::query()->where('code', 'FIXED_ONLY')->firstOrFail();
        $lab = Lab::query()->where('clinic_id', $clinic->id)->firstOrFail();

        $this->seedMinimumBusinessConfiguration($clinic, $lab, CommissionType::Fixed);

        $this->actingAs($owner)
            ->get(route('imports.index'))
            ->assertSee(BusinessConfigurationService::INCOMPLETE_MESSAGE, false);

        $doctor = Doctor::query()->where('clinic_id', $clinic->id)->firstOrFail();
        $treatment = Treatment::query()->where('clinic_id', $clinic->id)->firstOrFail();

        DoctorFixedFee::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'treatment_id' => $treatment->id,
            'fee_amount' => '500.00',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertDontSee(BusinessConfigurationService::INCOMPLETE_MESSAGE, false);
    }

    public function test_api_configuration_status_endpoint_returns_progress(): void
    {
        $owner = $this->registerClinicOwner('API_CFG', 'owner@apicfg.test');

        Sanctum::actingAs($owner);

        $this->getJson('/api/admin/configuration/status')
            ->assertOk()
            ->assertJsonPath('data.ready_for_import', false)
            ->assertJsonPath('data.current_step', ConfigurationProgressService::STEP_DOCTORS)
            ->assertJsonStructure([
                'data' => [
                    'progress_percentage',
                    'ready_for_import',
                    'missing_modules',
                    'current_step',
                    'steps',
                ],
            ]);
    }

    public function test_api_import_is_blocked_when_configuration_is_incomplete(): void
    {
        $owner = $this->registerClinicOwner('API_BLOCK', 'owner@apiblock.test');

        Sanctum::actingAs($owner);

        $file = UploadedFile::fake()->create('daily-report.xlsx', 128, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->postJson('/api/daily-reports/import', ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_clinic_111_still_allows_import(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin);

        $this->assertTrue(app(BusinessConfigurationService::class)->canImport());

        $this->actingAs($admin)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertDontSee(BusinessConfigurationService::INCOMPLETE_MESSAGE, false);
    }

    private function registerClinicOwner(string $code, string $email): User
    {
        $this->post(route('register-clinic.store'), [
            'clinic_name' => 'Feature Clinic',
            'clinic_code' => $code,
            'country' => 'United States',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'owner_name' => 'Feature Owner',
            'owner_email' => $email,
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
        ]);

        return $this->verifyUser(User::query()->where('email', $email)->firstOrFail());
    }

    private function seedMinimumBusinessConfiguration(
        Clinic $clinic,
        Lab $lab,
        CommissionType $commissionType,
    ): void {
        Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr Minimum',
            'code' => 'MIN_DOC',
            'commission_type' => $commissionType,
            'commission_percentage' => $commissionType === CommissionType::Percentage ? 25 : null,
            'default_lab_id' => $lab->id,
            'is_active' => true,
        ]);

        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'MIN_TX',
            'name' => 'Minimum Treatment',
            'has_lab_cost' => true,
            'is_active' => true,
        ]);

        LabPrice::query()->create([
            'clinic_id' => $clinic->id,
            'lab_id' => $lab->id,
            'treatment_id' => $treatment->id,
            'unit_cost' => '100.00',
            'currency' => $clinic->currency,
            'is_active' => true,
        ]);
    }
}
