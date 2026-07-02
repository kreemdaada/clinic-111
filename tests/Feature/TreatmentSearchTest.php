<?php

namespace Tests\Feature;

use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_search_filters_treatments_by_code(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('treatments.index', ['search' => 'ZIR']))
            ->assertOk()
            ->assertSee('Zircon Crown', false)
            ->assertDontSee('Metal Ceramic Crown', false);
    }

    public function test_search_without_matches_shows_empty_state(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('treatments.index', ['search' => 'NOTFOUND']))
            ->assertOk()
            ->assertSee('No treatments match your filters.', false);
    }

    public function test_index_without_search_shows_full_treatment_list(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $expectedCount = Treatment::query()->where('clinic_id', $admin->clinic_id)->count();

        $this->assertGreaterThan(1, $expectedCount);

        $this->actingAs($admin)
            ->get(route('treatments.index'))
            ->assertOk()
            ->assertSee('Metal Ceramic Crown', false)
            ->assertSee('Composite Filling', false);
    }

    public function test_empty_search_query_is_treated_as_no_search(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('treatments.index', ['search' => '']))
            ->assertOk()
            ->assertSee('Metal Ceramic Crown', false)
            ->assertSee('Composite Filling', false);
    }

    public function test_status_filter_works_without_search(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $inactive = Treatment::query()->where('code', 'CF')->firstOrFail();
        $inactive->is_active = false;
        $inactive->save();

        $this->actingAs($admin)
            ->get(route('treatments.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Composite Filling', false)
            ->assertDontSee('Zircon Crown', false);
    }

    public function test_status_filter_is_preserved_when_search_is_cleared_from_url(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('treatments.index', ['search' => 'ZIR', 'status' => 'active']))
            ->assertOk()
            ->assertSee('Zircon Crown', false);

        $this->actingAs($admin)
            ->get(route('treatments.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Metal Ceramic Crown', false)
            ->assertSee('Composite Filling', false);
    }

    public function test_treatment_index_includes_search_reset_handler(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('treatments.index', ['search' => 'ZIR', 'status' => 'active', 'page' => 2]))
            ->assertOk()
            ->assertSee('data-treatment-search-reset', false)
            ->assertSee('redirectWhenSearchCleared', false);
    }
}
