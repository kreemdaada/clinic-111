<?php

namespace Tests\Feature;

use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UI regression tests for the treatments admin page (modal edit/create fixes).
 */
class TreatmentAdminUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_index_renders_edit_buttons_with_data_attributes(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'MC')->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('treatments.index', ['page' => 1]));

        $response->assertOk();
        $response->assertSee('class="btn btn-secondary btn-sm tx-edit-btn"', false);
        $response->assertSee('data-update-url="'.route('treatments.update', $treatment).'"', false);
        $response->assertSee('data-activate-url="'.route('treatments.activate', $treatment).'"', false);
        $response->assertSee('data-destroy-url="'.route('treatments.destroy', $treatment).'"', false);
        $response->assertDontSee('data-edit-treatment="', false);
    }

    public function test_create_button_is_outside_filter_form(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $html = $this->actingAs($admin)
            ->get(route('treatments.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<\/form>\s*<div style="margin-bottom:1rem;">\s*<button[^>]+data-open-create/',
            $html,
        );
    }

    public function test_store_preserves_filters_via_return_params(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('treatments.index', ['search' => 'MC', 'status' => 'active']))
            ->post(route('treatments.store'), [
                '_form' => 'create',
                'return_search' => 'MC',
                'return_status' => 'active',
                'code' => 'UI-TX',
                'name' => 'UI Treatment',
                'description' => 'Created from UI test',
                'has_lab_cost' => '1',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('success');
        $this->assertStringContainsString('search=MC', $response->headers->get('Location'));
        $this->assertStringContainsString('status=active', $response->headers->get('Location'));

        $this->assertDatabaseHas('treatments', [
            'code' => 'UI-TX',
            'name' => 'UI Treatment',
        ]);
    }

    public function test_update_preserves_page_filter(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'CF')->firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('treatments.index', ['page' => 2, 'status' => 'active']))
            ->put(route('treatments.update', $treatment), [
                '_form' => 'edit',
                '_update_url' => route('treatments.update', $treatment),
                'return_page' => '2',
                'return_status' => 'active',
                'code' => 'CF',
                'name' => 'Composite Filling UI',
                'description' => 'UI update',
                'is_active' => '1',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('success');
        $this->assertStringContainsString('page=2', $response->headers->get('Location'));
        $this->assertStringContainsString('status=active', $response->headers->get('Location'));
    }

    public function test_update_validation_reopens_edit_modal(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'CF')->firstOrFail();

        $page = $this->actingAs($admin)
            ->from(route('treatments.index'))
            ->followingRedirects()
            ->put(route('treatments.update', $treatment), [
                '_form' => 'edit',
                '_update_url' => route('treatments.update', $treatment),
                'code' => '',
                'name' => '',
                'is_active' => '1',
            ]);

        $page->assertOk();
        $this->assertMatchesRegularExpression(
            '/id="tx-edit-modal"[\s\S]*?data-open-on-load="1"/',
            $page->getContent(),
        );
        $this->assertMatchesRegularExpression(
            '/id="tx-create-modal"[\s\S]*?data-open-on-load="0"/',
            $page->getContent(),
        );
    }

    public function test_activate_and_deactivate_via_ui_routes(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $treatment = Treatment::query()->create($this->withClinicId([
            'code' => 'UIACT',
            'name' => 'UI Activate Test',
        ]));
        $treatment->has_lab_cost = false;
        $treatment->is_active = true;
        $treatment->save();

        $this->actingAs($admin)
            ->delete(route('treatments.destroy', $treatment))
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHas('success');

        $this->assertFalse($treatment->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('treatments.activate', $treatment))
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHas('success');

        $this->assertTrue($treatment->fresh()->is_active);
    }
}
