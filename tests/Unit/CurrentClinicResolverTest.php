<?php

namespace Tests\Unit;

use App\Exceptions\CurrentClinicException;
use App\Models\Clinic;
use App\Models\User;
use App\Services\Configuration\CurrentClinicResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentClinicResolverTest extends TestCase
{
    use RefreshDatabase;

    private CurrentClinicResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->resolver = app(CurrentClinicResolver::class);
    }

    public function test_resolve_returns_authenticated_users_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $clinic = Clinic::query()->where('code', 'CLINIC_111')->firstOrFail();
        $this->actingAs($admin);

        $resolved = $this->resolver->resolve();

        $this->assertTrue($resolved->is($clinic));
        $this->assertSame($clinic->id, $this->resolver->resolveId());
    }

    public function test_resolve_throws_when_no_authenticated_user(): void
    {
        $this->expectException(CurrentClinicException::class);
        $this->expectExceptionMessage('No authenticated user');

        $this->resolver->resolve();
    }

    public function test_resolve_throws_when_user_has_no_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $admin->clinic_id = null;
        $this->actingAs($admin);

        $this->expectException(CurrentClinicException::class);
        $this->expectExceptionMessage('not assigned to a clinic');

        $this->resolver->resolve();
    }

    public function test_lab_management_service_assigns_current_clinic(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $this->actingAs($admin);

        $lab = app(\App\Services\Accounting\LabManagementService::class)->create([
            'name' => 'Resolver Lab',
            'code' => 'RESOLVER_LAB',
        ]);

        $this->assertSame($admin->clinic_id, $lab->clinic_id);
    }
}
