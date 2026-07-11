<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterClinicLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_register_clinic_form_in_english_by_default(): void
    {
        $this->get(route('register-clinic.create'))
            ->assertOk()
            ->assertSee(__('onboarding.register.heading', [], 'en'), false)
            ->assertSee(__('onboarding.register.sections.clinic_information', [], 'en'), false)
            ->assertSee(__('auth.create_clinic', [], 'en'), false)
            ->assertSee('name="locale"', false);
    }

    public function test_guest_sees_register_clinic_form_in_german_after_locale_switch(): void
    {
        $this->from(route('register-clinic.create'))
            ->put(route('locale.update'), ['locale' => 'de'])
            ->assertRedirect(route('register-clinic.create'));

        $this->get(route('register-clinic.create'))
            ->assertOk()
            ->assertSee(__('onboarding.register.heading', [], 'de'), false)
            ->assertSee(__('onboarding.register.labels.clinic_name', [], 'de'), false)
            ->assertSee(__('onboarding.currencies.AED', [], 'de'), false)
            ->assertSee(__('auth.already_have_account', [], 'de'), false)
            ->assertDontSee(__('onboarding.register.heading', [], 'en'), false);
    }

    public function test_guest_sees_register_clinic_form_in_arabic_with_rtl(): void
    {
        $this->from(route('register-clinic.create'))
            ->put(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect(route('register-clinic.create'));

        $response = $this->get(route('register-clinic.create'));

        $response->assertOk()
            ->assertSee(__('onboarding.register.heading', [], 'ar'), false)
            ->assertSee(__('onboarding.register.labels.email', [], 'ar'), false)
            ->assertSee('dir="rtl"', false);
    }
}
