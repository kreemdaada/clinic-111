<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Auth\LoginThrottleService;
use App\Support\AuditContext;
use App\Support\SecurePassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PlatformSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_unknown_login_audit_uses_platform_context_not_clinic_111(): void
    {
        $this->post(route('login'), [
            'email' => 'unknown@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $log = AuditLog::query()
            ->where('action', AuditAction::LoginFailed)
            ->latest('id')
            ->firstOrFail();

        $this->assertNull($log->clinic_id);
        $this->assertSame(AuditContext::PLATFORM, $log->new_values['audit_context'] ?? null);

        $clinic111 = $this->clinic111();
        $this->assertNotSame($clinic111->id, $log->clinic_id);
    }

    public function test_login_throttle_key_includes_user_agent(): void
    {
        $service = app(LoginThrottleService::class);
        $request = Request::create('/login', 'POST', server: [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 TestBrowser',
        ]);

        $key = $service->throttleKey('user@example.test', $request);

        $this->assertStringStartsWith('login|user@example.test|203.0.113.10|', $key);
        $this->assertNotSame('login|user@example.test|203.0.113.10', $key);
    }

    public function test_login_lockout_still_works_with_user_agent_key(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), [
                'email' => 'admin@clinic.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login'), [
            'email' => 'admin@clinic.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $errors = session('errors')->get('email');
        $this->assertStringContainsString('Too many login attempts', $errors[0]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LoginLockout->value,
        ]);
    }

    public function test_registration_captcha_can_block_request_when_enabled(): void
    {
        Config::set('auth_security.captcha.enabled', true);

        $this->post(route('register-clinic.store'), [
            'clinic_name' => 'Captcha Clinic',
            'clinic_code' => 'CAPTCHA_FAIL',
            'country' => 'United Arab Emirates',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@captcha-fail.test',
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
            'captcha_token' => 'invalid-token',
        ])->assertSessionHasErrors('captcha_token');

        $this->assertDatabaseMissing('clinics', ['code' => 'CAPTCHA_FAIL']);
    }

    public function test_registration_captcha_can_be_disabled_in_tests(): void
    {
        Config::set('auth_security.captcha.enabled', false);

        Notification::fake();

        $this->post(route('register-clinic.store'), [
            'clinic_name' => 'No Captcha Clinic',
            'clinic_code' => 'NO_CAPTCHA',
            'country' => 'United Arab Emirates',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@no-captcha.test',
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('clinics', ['code' => 'NO_CAPTCHA']);
    }

    public function test_registration_captcha_accepts_fake_token_when_enabled(): void
    {
        Config::set('auth_security.captcha.enabled', true);
        Config::set('auth_security.captcha.fake_token', 'test-captcha-token');

        Notification::fake();

        $this->post(route('register-clinic.store'), [
            'clinic_name' => 'Captcha Pass Clinic',
            'clinic_code' => 'CAPTCHA_PASS',
            'country' => 'United Arab Emirates',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@captcha-pass.test',
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
            'captcha_token' => 'test-captcha-token',
        ])->assertRedirect(route('verification.notice'));
    }

    public function test_email_verification_notification_is_sent_on_registration(): void
    {
        Notification::fake();

        $this->post(route('register-clinic.store'), [
            'clinic_name' => 'Verify Clinic',
            'clinic_code' => 'VERIFY_CLINIC',
            'country' => 'United Arab Emirates',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@verify-clinic.test',
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
        ])->assertRedirect(route('verification.notice'));

        $owner = User::query()->where('email', 'owner@verify-clinic.test')->firstOrFail();

        Notification::assertSentTo($owner, VerifyEmail::class);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::EmailVerificationSent->value,
            'clinic_id' => $owner->clinic_id,
        ]);
    }

    public function test_security_headers_are_present_when_enabled(): void
    {
        Config::set('security.headers.enabled', true);
        Config::set('security.headers.hsts.enabled', false);

        $response = $this->get(route('login'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_security_headers_are_disabled_by_default_in_tests(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_logout_is_audited(): void
    {
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::Logout->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_registration_abuse_is_audited_with_platform_context(): void
    {
        RateLimiter::clear('register-clinic:127.0.0.1');

        $payload = fn (int $index) => [
            'clinic_name' => "Abuse Clinic {$index}",
            'clinic_code' => "ABUSE{$index}",
            'country' => 'United Arab Emirates',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'owner_name' => "Owner {$index}",
            'owner_email' => "abuse{$index}@example.test",
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
        ];

        Notification::fake();

        for ($index = 1; $index <= 3; $index++) {
            $this->post(route('register-clinic.store'), $payload($index))
                ->assertRedirect(route('verification.notice'));

            $this->post(route('logout'));
        }

        $this->post(route('register-clinic.store'), $payload(4))
            ->assertStatus(429);

        $log = AuditLog::query()
            ->where('action', AuditAction::RegistrationAbuse)
            ->latest('id')
            ->firstOrFail();

        $this->assertNull($log->clinic_id);
        $this->assertSame(AuditContext::PLATFORM, $log->new_values['audit_context'] ?? null);
    }
}
