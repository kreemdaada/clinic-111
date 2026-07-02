<?php

namespace Tests\Unit;

use App\Enums\CommissionType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Configuration\ConfigurationProgressService;
use App\Support\SecurePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConfigurationProgressService $progressService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->progressService = app(ConfigurationProgressService::class);
    }

    public function test_clinic_111_is_ready_for_import(): void
    {
        $this->authenticateAdmin();

        $status = $this->progressService->status();

        $this->assertTrue($status['ready_for_import']);
        $this->assertSame(100, $status['progress_percentage']);
        $this->assertSame([], $status['missing_modules']);
        $this->assertSame(ConfigurationProgressService::STEP_IMPORT, $status['current_step']);
    }

    public function test_new_clinic_is_not_ready_for_import(): void
    {
        $owner = $this->registerClinicOwner('PROGRESS_CLINIC', 'owner@progress.test');

        $this->actingAs($owner);

        $status = $this->progressService->status();

        $this->assertFalse($status['ready_for_import']);
        $this->assertSame(50, $status['progress_percentage']);
        $this->assertSame(ConfigurationProgressService::STEP_DOCTORS, $status['current_step']);
        $this->assertContains(ConfigurationProgressService::STEP_DOCTORS, $status['missing_modules']);
        $this->assertContains(ConfigurationProgressService::STEP_LAB_PRICES, $status['missing_modules']);
        $this->assertNotContains(ConfigurationProgressService::STEP_TREATMENTS, $status['missing_modules']);
    }

    public function test_progress_updates_as_required_modules_are_added(): void
    {
        $owner = $this->registerClinicOwner('STEP_CLINIC', 'owner@step.test');
        $clinic = Clinic::query()->where('code', 'STEP_CLINIC')->firstOrFail();
        $lab = Lab::query()->where('clinic_id', $clinic->id)->firstOrFail();

        $this->actingAs($owner);

        Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr Step',
            'code' => 'STEP_DOC',
            'commission_type' => CommissionType::Percentage,
            'commission_percentage' => 30,
            'default_lab_id' => $lab->id,
            'is_active' => true,
        ]);

        $this->assertSame(75, $this->progressService->status()['progress_percentage']);
        $this->assertSame(ConfigurationProgressService::STEP_LAB_PRICES, $this->progressService->status()['current_step']);

        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'STEP_TX',
            'name' => 'Step Treatment',
            'has_lab_cost' => true,
            'is_active' => true,
        ]);

        $this->assertSame(75, $this->progressService->status()['progress_percentage']);

        LabPrice::query()->create([
            'clinic_id' => $clinic->id,
            'lab_id' => $lab->id,
            'treatment_id' => $treatment->id,
            'unit_cost' => '100.00',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $status = $this->progressService->status();

        $this->assertTrue($status['ready_for_import']);
        $this->assertSame(100, $status['progress_percentage']);
    }

    public function test_percentage_only_clinic_does_not_require_fixed_fees(): void
    {
        $owner = $this->registerClinicOwner('PCT_CLINIC', 'owner@pct.test');
        $clinic = Clinic::query()->where('code', 'PCT_CLINIC')->firstOrFail();
        $lab = Lab::query()->where('clinic_id', $clinic->id)->firstOrFail();

        $this->actingAs($owner);
        $this->seedMinimumBusinessConfiguration($clinic, $lab, CommissionType::Percentage);

        $fixedFeesStep = collect($this->progressService->status()['steps'])
            ->firstWhere('key', ConfigurationProgressService::STEP_DOCTOR_FIXED_FEES);

        $this->assertTrue($fixedFeesStep['completed']);
        $this->assertFalse($fixedFeesStep['required']);
        $this->assertTrue($this->progressService->readyForImport());
    }

    public function test_fixed_fee_doctors_require_active_fee_rules(): void
    {
        $owner = $this->registerClinicOwner('FIXED_CLINIC', 'owner@fixed.test');
        $clinic = Clinic::query()->where('code', 'FIXED_CLINIC')->firstOrFail();
        $lab = Lab::query()->where('clinic_id', $clinic->id)->firstOrFail();

        $this->actingAs($owner);
        $this->seedMinimumBusinessConfiguration($clinic, $lab, CommissionType::Fixed);

        $status = $this->progressService->status();

        $this->assertFalse($status['ready_for_import']);
        $this->assertSame(ConfigurationProgressService::STEP_DOCTOR_FIXED_FEES, $status['current_step']);

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

        $this->assertTrue($this->progressService->readyForImport());
    }

    private function registerClinicOwner(string $code, string $email): User
    {
        $this->post(route('register-clinic.store'), [
            'clinic_name' => 'Progress Clinic',
            'clinic_code' => $code,
            'country' => 'United States',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'owner_name' => 'Progress Owner',
            'owner_email' => $email,
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
        ]);

        return User::query()->where('email', $email)->firstOrFail();
    }

    private function seedMinimumBusinessConfiguration(
        Clinic $clinic,
        Lab $lab,
        CommissionType $commissionType,
    ): void {
        $doctor = Doctor::query()->create([
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

        unset($doctor);
    }
}
