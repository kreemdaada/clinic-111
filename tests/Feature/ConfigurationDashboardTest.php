<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_admin_can_view_configuration_dashboard(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('configuration.dashboard'))
            ->assertOk()
            ->assertSee('Configuration')
            ->assertSee('Recent activity')
            ->assertSee('Configuration health')
            ->assertSee('Doctors', false)
            ->assertSee('Manage doctors', false)
            ->assertSee(route('labs.index'), false);
    }

    public function test_viewer_cannot_access_configuration_dashboard(): void
    {
        $viewer = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('configuration.dashboard'))
            ->assertForbidden();
    }

    public function test_accountant_cannot_access_configuration_dashboard(): void
    {
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $this->actingAs($accountant)
            ->get(route('configuration.dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_shows_module_counts(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctorCount = Doctor::query()->count();
        $labCount = Lab::query()->count();

        $response = $this->actingAs($admin)->get(route('configuration.dashboard'));

        $response->assertOk();
        $response->assertSee((string) $doctorCount, false);
        $response->assertSee((string) $labCount, false);
    }

    public function test_dashboard_shows_health_warning_when_all_labs_inactive(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        Lab::query()->update(['is_active' => false]);

        $this->actingAs($admin)
            ->get(route('configuration.dashboard'))
            ->assertOk()
            ->assertSee('No active laboratories are configured.', false);
    }

    public function test_navigation_links_to_configuration_dashboard(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee(route('configuration.dashboard'), false)
            ->assertSee('Configuration', false);
    }
}
