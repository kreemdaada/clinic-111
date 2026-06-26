<?php

namespace Tests\Unit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Configuration\ConfigurationDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConfigurationDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->service = app(ConfigurationDashboardService::class);
    }

    public function test_module_statistics_include_all_configuration_modules(): void
    {
        $modules = $this->service->moduleStatistics();
        $keys = array_column($modules, 'key');

        $this->assertSame(
            ['doctors', 'labs', 'treatments', 'lab_prices', 'doctor_fixed_fees', 'users'],
            $keys,
        );

        $doctors = collect($modules)->firstWhere('key', 'doctors');
        $this->assertGreaterThan(0, $doctors['total']);
        $this->assertSame($doctors['total'], $doctors['active'] + $doctors['inactive']);
        $this->assertSame('doctors.index', $doctors['index_route']);
    }

    public function test_recent_activity_includes_configuration_audit_logs(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => AuditAction::DoctorUpdated,
            'auditable_type' => $doctor->getMorphClass(),
            'auditable_id' => $doctor->id,
            'old_values' => ['name' => 'Old'],
            'new_values' => ['name' => 'New'],
        ]);

        $activity = $this->service->recentActivity();

        $this->assertNotEmpty($activity);
        $this->assertSame('admin@clinic.test', $activity[0]['user']);
        $this->assertSame('doctor updated', $activity[0]['action']);
        $this->assertSame('JACK', $activity[0]['target']);
    }

    public function test_health_warnings_when_no_active_laboratories(): void
    {
        Lab::query()->update(['is_active' => false]);

        $warnings = $this->service->healthWarnings();
        $codes = array_column($warnings, 'code');

        $this->assertContains('no_active_labs', $codes);
    }

    public function test_health_warnings_for_lab_cost_treatment_without_price(): void
    {
        $treatment = Treatment::query()->create([
            'code' => 'WARN_LP',
            'name' => 'Warning Treatment',
        ]);
        $treatment->has_lab_cost = true;
        $treatment->is_active = true;
        $treatment->save();

        LabPrice::query()->where('treatment_id', $treatment->id)->update(['is_active' => false]);

        $warnings = $this->service->healthWarnings();
        $messages = array_column($warnings, 'message');

        $this->assertTrue(
            collect($messages)->contains(fn (string $message) => str_contains($message, 'WARN_LP')),
        );
    }

    public function test_build_dashboard_returns_expected_structure(): void
    {
        $dashboard = $this->service->buildDashboard();

        $this->assertArrayHasKey('modules', $dashboard);
        $this->assertArrayHasKey('recent_activity', $dashboard);
        $this->assertArrayHasKey('health_warnings', $dashboard);
        $this->assertCount(6, $dashboard['modules']);
    }
}
