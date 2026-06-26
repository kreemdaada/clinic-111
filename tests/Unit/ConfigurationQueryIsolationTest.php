<?php

namespace Tests\Unit;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\User;
use App\Services\Accounting\LabManagementService;
use App\Services\Configuration\ConfigurationDashboardService;
use App\Services\DailyReport\DoctorManagementService;
use App\Services\User\UserManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationQueryIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $clinic222;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->clinic222 = $this->seedClinic222Tenant();
    }

    public function test_lab_management_service_list_query_filters_by_current_clinic(): void
    {
        $service = app(LabManagementService::class);

        $this->authenticateAdmin();
        $codes111 = $service->listQuery()->pluck('code')->all();
        $this->assertContains('MAIN_LAB', $codes111);
        $this->assertNotContains('C222_LAB', $codes111);

        $this->actingAs($this->clinic222['admin']);
        $codes222 = $service->listQuery()->pluck('code')->all();
        $this->assertContains('C222_LAB', $codes222);
        $this->assertNotContains('MAIN_LAB', $codes222);
    }

    public function test_doctor_management_service_list_query_filters_by_current_clinic(): void
    {
        $service = app(DoctorManagementService::class);

        $this->authenticateAdmin();
        $codes111 = $service->listQuery()->pluck('code')->all();
        $this->assertContains('JACK', $codes111);
        $this->assertNotContains('C222_DOC', $codes111);

        $this->actingAs($this->clinic222['admin']);
        $codes222 = $service->listQuery()->pluck('code')->all();
        $this->assertContains('C222_DOC', $codes222);
        $this->assertNotContains('JACK', $codes222);
    }

    public function test_user_management_service_list_query_filters_by_current_clinic(): void
    {
        $service = app(UserManagementService::class);

        $this->authenticateAdmin();
        $emails111 = $service->listQuery()->pluck('email')->all();
        $this->assertContains('admin@clinic.test', $emails111);
        $this->assertNotContains('admin@clinic222.test', $emails111);

        $this->actingAs($this->clinic222['admin']);
        $emails222 = $service->listQuery()->pluck('email')->all();
        $this->assertContains('admin@clinic222.test', $emails222);
        $this->assertNotContains('admin@clinic.test', $emails222);
    }

    public function test_dashboard_module_counts_are_clinic_specific(): void
    {
        $service = app(ConfigurationDashboardService::class);

        $this->authenticateAdmin();
        $modules111 = collect($service->moduleStatistics());
        $doctors111 = $modules111->firstWhere('key', 'doctors');
        $totalDoctors111 = Doctor::query()->where('clinic_id', $this->clinic111()->id)->count();
        $this->assertSame($totalDoctors111, $doctors111['total']);

        $this->actingAs($this->clinic222['admin']);
        $modules222 = collect($service->moduleStatistics());
        $doctors222 = $modules222->firstWhere('key', 'doctors');
        $this->assertSame(1, $doctors222['total']);
        $this->assertLessThan($doctors111['total'], $doctors222['total']);
    }

    public function test_dashboard_health_warnings_ignore_other_clinic_data(): void
    {
        Lab::query()->where('clinic_id', $this->clinic111()->id)->update(['is_active' => false]);

        $service = app(ConfigurationDashboardService::class);

        $this->actingAs($this->clinic222['admin']);
        $warnings222 = collect($service->healthWarnings())->pluck('code');
        $this->assertFalse($warnings222->contains('no_active_labs'));

        $this->authenticateAdmin();
        $warnings111 = collect($service->healthWarnings())->pluck('code');
        $this->assertTrue($warnings111->contains('no_active_labs'));
    }
}
