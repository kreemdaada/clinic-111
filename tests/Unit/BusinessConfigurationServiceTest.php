<?php

namespace Tests\Unit;

use App\Exceptions\BusinessConfigurationIncompleteException;
use App\Models\Clinic;
use App\Models\User;
use App\Services\Configuration\BusinessConfigurationService;
use App\Support\SecurePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessConfigurationServiceTest extends TestCase
{
    use RefreshDatabase;

    private BusinessConfigurationService $businessConfigurationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->businessConfigurationService = app(BusinessConfigurationService::class);
    }

    public function test_can_import_for_fully_configured_clinic(): void
    {
        $this->authenticateAdmin();

        $this->assertTrue($this->businessConfigurationService->canImport());
        $this->businessConfigurationService->assertReadyForImport();
    }

    public function test_cannot_import_for_new_clinic(): void
    {
        $owner = $this->registerClinicOwner();

        $this->actingAs($owner);

        $this->assertFalse($this->businessConfigurationService->canImport());

        $this->expectException(BusinessConfigurationIncompleteException::class);
        $this->businessConfigurationService->assertReadyForImport();
    }

    public function test_status_includes_progress_fields(): void
    {
        $owner = $this->registerClinicOwner();
        $this->actingAs($owner);

        $status = $this->businessConfigurationService->status();

        $this->assertArrayHasKey('progress_percentage', $status);
        $this->assertArrayHasKey('missing_modules', $status);
        $this->assertArrayHasKey('current_step', $status);
        $this->assertArrayHasKey('ready_for_import', $status);
        $this->assertArrayHasKey('steps', $status);
    }

    private function registerClinicOwner(): User
    {
        $this->post(route('register-clinic.store'), [
            'clinic_name' => 'Business Config Clinic',
            'clinic_code' => 'BIZ_CFG',
            'country' => 'United States',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'owner_name' => 'Biz Owner',
            'owner_email' => 'owner@bizcfg.test',
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
        ]);

        return User::query()->where('email', 'owner@bizcfg.test')->firstOrFail();
    }
}
