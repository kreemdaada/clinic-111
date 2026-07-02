<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminHelperTextRemovalTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const REMOVED_HELPER_SNIPPETS = [
        'commission rates are read from the database',
        'configure procedure codes',
        'configure unit costs per lab and treatment',
        'per-treatment fees for doctors who are',
        'Percentage doctors ignore these rules',
        'manage external labs',
        'historical reports keep their lab references',
        'Upload your daily Excel',
        'review the extraction log',
        'download the Server Income file',
    ];

    public function test_admin_configuration_pages_remain_accessible_without_technical_helper_texts(): void
    {
        $this->seedAccountingData();

        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $pages = [
            ['route' => 'doctors.index', 'title' => 'Doctors', 'marker' => 'Commission type'],
            ['route' => 'treatments.index', 'title' => 'Treatments', 'marker' => 'Lab cost'],
            ['route' => 'lab-prices.index', 'title' => 'Lab prices', 'marker' => 'Unit cost'],
            ['route' => 'doctor-fixed-fees.index', 'title' => 'Doctors without commission', 'marker' => 'Amount'],
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($admin)->get(route($page['route']));

            $response->assertOk();
            $response->assertSee($page['title'], false);
            $response->assertSee($page['marker'], false);

            foreach (self::REMOVED_HELPER_SNIPPETS as $snippet) {
                $response->assertDontSee($snippet, false);
            }
        }
    }

    public function test_labs_and_import_pages_remain_accessible_without_technical_helper_texts(): void
    {
        $this->seedAccountingData();

        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $pages = [
            ['route' => 'labs.index', 'title' => 'Laboratories', 'marker' => 'Create laboratory'],
            ['route' => 'imports.index', 'title' => 'Import Daily Report', 'marker' => 'Import file'],
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($admin)->get(route($page['route']));

            $response->assertOk();
            $response->assertSee($page['title'], false);
            $response->assertSee($page['marker'], false);

            foreach (self::REMOVED_HELPER_SNIPPETS as $snippet) {
                $response->assertDontSee($snippet, false);
            }
        }

        $this->actingAs($admin)
            ->get(route('imports.index'))
            ->assertSee('Drop Excel file here', false);
    }
}
