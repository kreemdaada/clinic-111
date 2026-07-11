<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_with_locale_de_sets_app_locale(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk();

        $this->assertSame('de', app()->getLocale());
    }

    public function test_authenticated_user_with_locale_ar_sets_app_locale_and_rtl(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'ar']);

        $response = $this->actingAs($user)->get(route('settings.language.edit'));

        $response->assertOk();
        $this->assertSame('ar', app()->getLocale());
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="ar"', false);
    }

    public function test_user_without_custom_locale_falls_back_to_english(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'en']);

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_german_user_sees_ltr_direction(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('settings.language.edit'))
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('lang="de"', false);
    }

    public function test_invalid_locale_is_rejected_on_update(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->from(route('settings.language.edit'))
            ->put(route('settings.language.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_user_can_switch_language_to_german(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->from(route('settings.language.edit'))
            ->put(route('settings.language.update'), ['locale' => 'de'])
            ->assertRedirect(route('settings.language.edit'))
            ->assertSessionHas('status', __('settings.language.saved'));

        $this->assertSame('de', $user->fresh()->locale);
    }

    public function test_user_can_switch_language_to_arabic(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->put(route('settings.language.update'), ['locale' => 'ar'])
            ->assertRedirect();

        $this->assertSame('ar', $user->fresh()->locale);
    }

    public function test_user_can_switch_language_to_english(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->put(route('settings.language.update'), ['locale' => 'en'])
            ->assertRedirect();

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_language_settings_page_uses_translations(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('settings.language.edit'))
            ->assertOk()
            ->assertSee('Sprache', false)
            ->assertSee('Speichern', false);
    }
}
