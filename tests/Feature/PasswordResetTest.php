<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\User;
use App\Support\SecurePassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        Notification::fake();
    }

    public function test_login_page_shows_forgot_password_link(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'), false)
            ->assertSee(__('auth.forgot_password'), false);
    }

    public function test_guest_can_view_forgot_password_form(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee(__('auth.forgot_password_title'), false);
    }

    public function test_forgot_password_sends_mail_for_active_user_and_audits_request(): void
    {
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertSentTo($user, ResetPassword::class);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PasswordResetRequested->value,
            'clinic_id' => $user->clinic_id,
        ]);
    }

    public function test_forgot_password_is_generic_for_unknown_email(): void
    {
        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'missing@example.test'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertNothingSent();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PasswordResetRequested->value,
            'clinic_id' => null,
        ]);
    }

    public function test_forgot_password_does_not_email_inactive_user(): void
    {
        $user = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();
        $user->forceFill(['is_active' => false])->save();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertNothingSent();
    }

    public function test_guest_can_reset_password_with_valid_token(): void
    {
        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $token = app('auth.password.broker')->getRepository()->create($user);
        $password = SecurePassword::example();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', __('passwords.reset'));

        $this->assertTrue(Hash::check($password, $user->fresh()->password));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PasswordReset->value,
            'auditable_id' => $user->id,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect(route('imports.index'));
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $password = SecurePassword::example();

        $this->from(route('password.reset', ['token' => 'invalid-token']))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->assertRedirect(route('password.reset', ['token' => 'invalid-token']))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
