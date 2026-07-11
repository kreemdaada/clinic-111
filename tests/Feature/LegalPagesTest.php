<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_guest_can_open_imprint(): void
    {
        $this->get(route('legal.imprint'))
            ->assertOk()
            ->assertSee('<h1>Impressum</h1>', false);
    }

    public function test_guest_can_open_privacy(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('<h1>Datenschutzerklärung</h1>', false);
    }

    public function test_authenticated_user_can_open_both_legal_pages(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('legal.imprint'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('legal.privacy'))
            ->assertOk();
    }

    public function test_landing_page_links_to_legal_pages(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee(route('legal.imprint'), false)
            ->assertSee(route('legal.privacy'), false)
            ->assertDontSee('coming soon', false);
    }

    public function test_landing_page_contact_link_uses_mailto_when_email_configured(): void
    {
        config([
            'legal.email' => 'contact@dentalfinance.test',
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('href="mailto:contact@dentalfinance.test"', false)
            ->assertSee(__('landing.footer.contact'), false);
    }

    public function test_login_and_registration_routes_remain_available(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('register-clinic.create'))->assertOk();
    }

    public function test_legal_pages_use_german_html_lang(): void
    {
        $this->get(route('legal.imprint'))
            ->assertOk()
            ->assertSee('<html lang="de">', false);

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('<html lang="de">', false);
    }

    public function test_imprint_has_canonical_url(): void
    {
        $this->get(route('legal.imprint'))
            ->assertOk()
            ->assertSee(route('legal.imprint'), false);
    }

    public function test_privacy_has_canonical_url(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee(route('legal.privacy'), false);
    }
}
