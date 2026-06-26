<?php

namespace Tests\Unit;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Configuration\ClinicOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class ClinicOnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClinicOnboardingService $clinicOnboardingService;

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'clinic_name' => 'Harbor Dental',
            'clinic_code' => 'HARBOR',
            'country' => 'United States',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'owner_name' => 'Harbor Owner',
            'owner_email' => 'owner@harbor.test',
            'owner_password' => 'password123',
        ], $overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->clinicOnboardingService = app(ClinicOnboardingService::class);
    }

    public function test_register_creates_clinic_owner_and_default_lab(): void
    {
        $result = $this->clinicOnboardingService->register($this->validPayload());

        $this->assertSame('HARBOR', $result['clinic']->code);
        $this->assertSame('Harbor Dental', $result['clinic']->name);
        $this->assertTrue($result['clinic']->is_active);

        $this->assertSame(UserRole::Admin, $result['owner']->role);
        $this->assertSame($result['clinic']->id, $result['owner']->clinic_id);
        $this->assertTrue($result['owner']->is_active);
        $this->assertTrue(Hash::check('password123', $result['owner']->password));

        $this->assertSame('HARBOR_MAIN_LAB', $result['default_lab']->code);
        $this->assertSame($result['clinic']->id, $result['default_lab']->clinic_id);
    }

    public function test_register_assigns_owner_to_new_clinic(): void
    {
        $result = $this->clinicOnboardingService->register($this->validPayload([
            'clinic_code' => 'OWNERSHIP',
            'owner_email' => 'owner@ownership.test',
        ]));

        $this->assertDatabaseHas('users', [
            'email' => 'owner@ownership.test',
            'clinic_id' => $result['clinic']->id,
            'role' => UserRole::Admin->value,
        ]);
    }

    public function test_register_creates_minimal_default_configuration_only(): void
    {
        $result = $this->clinicOnboardingService->register($this->validPayload([
            'clinic_code' => 'MINIMAL',
            'owner_email' => 'owner@minimal.test',
        ]));

        $clinicId = $result['clinic']->id;

        $this->assertSame(1, Lab::query()->where('clinic_id', $clinicId)->count());
        $this->assertSame(0, Doctor::query()->where('clinic_id', $clinicId)->count());
        $this->assertSame(0, Treatment::query()->where('clinic_id', $clinicId)->count());
    }

    public function test_register_normalizes_clinic_code_and_currency(): void
    {
        $result = $this->clinicOnboardingService->register($this->validPayload([
            'clinic_code' => 'lowercase',
            'currency' => 'eur',
            'owner_email' => 'owner@normalize.test',
        ]));

        $this->assertSame('LOWERCASE', $result['clinic']->code);
        $this->assertSame('EUR', $result['clinic']->currency);
    }

    public function test_register_rolls_back_when_audit_logging_fails(): void
    {
        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('logClinicCreated')->once();
            $mock->shouldReceive('logUserCreated')->once()->andThrow(new RuntimeException('Simulated failure'));
        });

        $this->expectException(RuntimeException::class);

        try {
            app(ClinicOnboardingService::class)->register($this->validPayload([
                'clinic_code' => 'ROLLBACK',
                'owner_email' => 'owner@rollback.test',
            ]));
        } finally {
            $this->assertDatabaseMissing('clinics', ['code' => 'ROLLBACK']);
            $this->assertDatabaseMissing('users', ['email' => 'owner@rollback.test']);
        }
    }

    public function test_register_writes_audit_logs_for_clinic_user_and_lab(): void
    {
        $result = $this->clinicOnboardingService->register($this->validPayload([
            'clinic_code' => 'AUDITED',
            'owner_email' => 'owner@audited.test',
        ]));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ClinicCreated->value,
            'auditable_id' => $result['clinic']->id,
            'clinic_id' => $result['clinic']->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserCreated->value,
            'auditable_id' => $result['owner']->id,
            'clinic_id' => $result['clinic']->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LabCreated->value,
            'auditable_id' => $result['default_lab']->id,
            'clinic_id' => $result['clinic']->id,
        ]);
    }
}
