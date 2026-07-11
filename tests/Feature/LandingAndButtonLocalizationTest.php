<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingAndButtonLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_landing_page_shows_english_copy_by_default(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee(__('landing.hero.title'), false)
            ->assertSee(__('landing.actions.get_started'), false)
            ->assertSee(__('landing.actions.login'), false)
            ->assertSee('lang="en"', false);
    }

    public function test_guest_landing_page_shows_german_copy_with_session_locale(): void
    {
        $this->withSession(['locale' => 'de'])
            ->get(route('landing'))
            ->assertOk()
            ->assertSee(__('landing.hero.title', [], 'de'), false)
            ->assertSee(__('landing.actions.get_started', [], 'de'), false)
            ->assertSee('lang="de"', false)
            ->assertDontSee('>Start free trial<', false);
    }

    public function test_guest_landing_page_shows_arabic_copy_with_rtl(): void
    {
        $response = $this->withSession(['locale' => 'ar'])
            ->get(route('landing'));

        $response->assertOk()
            ->assertSee(__('landing.hero.title', [], 'ar'), false)
            ->assertSee(__('landing.actions.login', [], 'ar'), false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="ar"', false);

        $this->assertDoesNotMatchRegularExpression(
            '/[٠-٩۰-۹]/u',
            (string) $response->getContent(),
            'Arabic-Indic digits found on landing page mockup',
        );
    }

    public function test_german_user_sees_translated_buttons_on_key_admin_pages(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee(__('import.index.start_import', [], 'de'), false)
            ->assertDontSee('>Start import<', false);

        $this->actingAs($user)
            ->get(route('labs.index'))
            ->assertOk()
            ->assertSee(__('common.actions.save', [], 'de'), false)
            ->assertDontSee('>Save<', false);
    }

    public function test_arabic_user_sees_translated_buttons_on_key_admin_pages(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'ar']);

        $this->actingAs($user)
            ->get(route('daily-report.index'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('daily_reports.actions.create_report', [], 'ar'), false)
            ->assertDontSee('>Create report<', false);
    }
}
