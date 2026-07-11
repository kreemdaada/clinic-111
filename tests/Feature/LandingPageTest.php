<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_landing_page_at_root(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee(__('landing.hero.title'), false)
            ->assertSee(__('landing.actions.get_started'), false)
            ->assertSee(route('register-clinic.create'), false)
            ->assertSee(route('login'), false);
    }

    public function test_authenticated_users_are_redirected_from_landing_to_imports(): void
    {
        $this->seedAccountingData();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('landing'))
            ->assertRedirect(route('imports.index'));
    }

    public function test_unverified_users_are_redirected_from_landing_to_verification_notice(): void
    {
        $this->seedAccountingData();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)
            ->get(route('landing'))
            ->assertRedirect(route('verification.notice'));
    }
}
