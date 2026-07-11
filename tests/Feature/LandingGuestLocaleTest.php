<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingGuestLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_landing_page_shows_language_dropdown(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('name="locale"', false)
            ->assertSee(route('locale.update'), false)
            ->assertSee(__('settings.language.options.en'), false)
            ->assertSee(__('settings.language.options.de'), false)
            ->assertSee(__('settings.language.options.ar'), false);
    }

    public function test_guest_can_switch_landing_locale_to_german(): void
    {
        $this->from(route('landing'))
            ->put(route('locale.update'), ['locale' => 'de'])
            ->assertRedirect(route('landing'));

        $this->assertSame('de', session('locale'));

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee(__('landing.hero.title', [], 'de'), false)
            ->assertSee(__('landing.features.cards.excel_import.title', [], 'de'), false)
            ->assertSee(__('landing.footer.imprint', [], 'de'), false)
            ->assertSee('lang="de"', false)
            ->assertDontSee(__('landing.hero.title', [], 'en'), false);
    }

    public function test_guest_can_switch_landing_locale_to_arabic_with_rtl(): void
    {
        $this->from(route('landing'))
            ->put(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect(route('landing'));

        $this->assertSame('ar', session('locale'));

        $response = $this->get(route('landing'));

        $response->assertOk()
            ->assertSee(__('landing.hero.title', [], 'ar'), false)
            ->assertSee(__('landing.actions.get_started', [], 'ar'), false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="ar"', false);

        $this->assertDoesNotMatchRegularExpression(
            '/[٠-٩۰-۹]/u',
            (string) $response->getContent(),
        );
    }

    public function test_guest_locale_update_rejects_invalid_locale(): void
    {
        $this->from(route('landing'))
            ->put(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertNull(session('locale'));
    }

    public function test_authenticated_user_can_still_switch_language_via_settings_route(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->from(route('imports.index'))
            ->put(route('settings.language.update'), ['locale' => 'de'])
            ->assertRedirect(route('imports.index'));

        $this->assertSame('de', $user->fresh()->locale);
        $this->assertSame('de', session('locale'));
    }

    public function test_authenticated_user_can_switch_language_via_public_locale_route(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->from(route('imports.index'))
            ->put(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect(route('imports.index'));

        $this->assertSame('ar', $user->fresh()->locale);
        $this->assertSame('ar', session('locale'));
    }
}
