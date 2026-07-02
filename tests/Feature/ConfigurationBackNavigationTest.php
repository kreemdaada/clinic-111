<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ConfigurationReturnContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationBackNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_configuration_dashboard_links_include_return_context(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('configuration.dashboard'));

        $response->assertOk();

        foreach ([
            'doctors.index',
            'admin.users.index',
            'treatments.index',
            'labs.index',
            'lab-prices.index',
            'doctor-fixed-fees.index',
        ] as $routeName) {
            $response->assertSee(route($routeName, ConfigurationReturnContext::query()), false);
        }
    }

    public function test_admin_pages_show_back_button_with_configuration_context(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        foreach ([
            'doctors.index',
            'admin.users.index',
            'treatments.index',
            'labs.index',
            'lab-prices.index',
            'doctor-fixed-fees.index',
        ] as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName, ConfigurationReturnContext::query()))
                ->assertOk()
                ->assertSee('Back to Configuration', false)
                ->assertSee(route('configuration.dashboard'), false);
        }
    }

    public function test_direct_access_without_context_has_no_back_button(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('doctors.index'))
            ->assertOk()
            ->assertDontSee('Back to Configuration', false);
    }

    public function test_search_and_pagination_preserve_configuration_context(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('treatments.index', array_merge(ConfigurationReturnContext::query(), [
                'search' => 'MC',
            ])));

        $response->assertOk()
            ->assertSee('Back to Configuration', false)
            ->assertSee('name="from" value="configuration"', false);
    }

    public function test_crud_redirect_preserves_configuration_context(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('labs.index', ConfigurationReturnContext::query()))
            ->post(route('labs.store'), [
                'name' => 'Context Lab',
                'code' => 'CTX_LAB',
                'return_from' => ConfigurationReturnContext::VALUE,
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('from=configuration', (string) $response->headers->get('Location'));
    }

    public function test_unknown_return_context_is_ignored(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('doctors.index', ['from' => 'https://evil.example']))
            ->assertOk()
            ->assertDontSee('Back to Configuration', false);

        $response = $this->actingAs($admin)
            ->from(route('doctors.index', ['from' => 'https://evil.example']))
            ->post(route('doctors.store'), [
                'code' => 'EVIL',
                'name' => 'Evil Doctor',
                'commission_type' => 'percentage',
                'commission_percentage' => '35',
                'return_from' => 'https://evil.example',
            ]);

        $response->assertRedirect(route('doctors.index'));
        $this->assertStringNotContainsString('evil.example', (string) $response->headers->get('Location'));
    }
}
