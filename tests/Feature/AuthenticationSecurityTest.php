<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\User;
use App\Services\Auth\AuthenticationService;
use App\Support\SecurePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_web_login_succeeds_with_valid_credentials(): void
    {
        $this->post(route('login'), [
            'email' => 'accountant@clinic.test',
            'password' => 'password',
        ])
            ->assertRedirect(route('imports.index'));

        $this->assertAuthenticatedAs(User::query()->where('email', 'accountant@clinic.test')->firstOrFail());

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LoginSucceeded->value,
        ]);
    }

    public function test_web_login_fails_with_invalid_credentials(): void
    {
        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'accountant@clinic.test',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $errors = session('errors')->get('email');
        $this->assertSame(AuthenticationService::INVALID_CREDENTIALS_MESSAGE, $errors[0]);
        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LoginFailed->value,
        ]);
    }

    public function test_login_uses_same_message_for_unknown_email_and_wrong_password(): void
    {
        $this->post(route('login'), [
            'email' => 'missing@clinic.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $unknownEmailMessage = session('errors')->get('email')[0];

        $this->post(route('login'), [
            'email' => 'accountant@clinic.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $wrongPasswordMessage = session('errors')->get('email')[0];

        $this->assertSame(AuthenticationService::INVALID_CREDENTIALS_MESSAGE, $unknownEmailMessage);
        $this->assertSame(AuthenticationService::INVALID_CREDENTIALS_MESSAGE, $wrongPasswordMessage);
    }

    public function test_login_locks_after_five_failed_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))
                ->post(route('login'), [
                    'email' => 'admin@clinic.test',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'admin@clinic.test',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $errors = session('errors')->get('email');
        $this->assertStringContainsString('Too many login attempts', $errors[0]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LoginLockout->value,
        ]);
    }

    public function test_login_unlocks_after_timeout(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), [
                'email' => 'admin@clinic.test',
                'password' => 'wrong-password',
            ]);
        }

        $this->travel(301)->seconds();

        $this->post(route('login'), [
            'email' => 'admin@clinic.test',
            'password' => 'password',
        ])->assertRedirect(route('imports.index'));

        $this->assertAuthenticated();
    }

    public function test_successful_login_resets_failed_attempt_counter(): void
    {
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post(route('login'), [
                'email' => 'viewer@clinic.test',
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('login'), [
            'email' => 'viewer@clinic.test',
            'password' => 'password',
        ])->assertRedirect(route('imports.index'));

        Auth::logout();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post(route('login'), [
                'email' => 'viewer@clinic.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login'), [
            'email' => 'viewer@clinic.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $errors = session('errors')->get('email');
        $this->assertSame(AuthenticationService::INVALID_CREDENTIALS_MESSAGE, $errors[0]);
    }

    public function test_api_login_succeeds_and_fails_generically(): void
    {
        $this->postJson('/api/login', [
            'email' => 'admin@clinic.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);

        $this->postJson('/api/login', [
            'email' => 'admin@clinic.test',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_web_login_regenerates_session(): void
    {
        $this->get(route('login'));
        $originalSessionId = session()->getId();

        $this->post(route('login'), [
            'email' => 'admin@clinic.test',
            'password' => 'password',
        ])->assertRedirect(route('imports.index'));

        $this->assertNotSame($originalSessionId, session()->getId());
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($user);
        $sessionId = session()->getId();

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotSame($sessionId, session()->getId());
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        RateLimiter::clear('register-clinic:127.0.0.1');

        $payload = fn (int $index) => [
            'clinic_name' => "Clinic {$index}",
            'clinic_code' => "RATE{$index}",
            'country' => 'United Arab Emirates',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'owner_name' => "Owner {$index}",
            'owner_email' => "owner{$index}@rate-limit.test",
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
        ];

        for ($index = 1; $index <= 3; $index++) {
            $this->post(route('register-clinic.store'), $payload($index))
                ->assertRedirect(route('configuration.dashboard'));

            Auth::logout();
        }

        $this->post(route('register-clinic.store'), $payload(4))
            ->assertStatus(429);
    }

    public function test_duplicate_clinic_code_is_rejected_with_generic_message(): void
    {
        $this->post(route('register-clinic.store'), [
            'clinic_name' => 'Duplicate Clinic',
            'clinic_code' => 'CLINIC_111',
            'country' => 'United Arab Emirates',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'owner_name' => 'Owner',
            'owner_email' => 'duplicate-clinic@example.test',
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
        ])->assertSessionHasErrors('clinic_code');

        $errors = session('errors')->get('clinic_code');
        $this->assertSame(
            'Registration could not be completed. Please check your details and try again.',
            $errors[0],
        );
    }

    public function test_duplicate_email_is_rejected_with_generic_message(): void
    {
        $this->post(route('register-clinic.store'), [
            'clinic_name' => 'Duplicate Email Clinic',
            'clinic_code' => 'DUP_EMAIL',
            'country' => 'United Arab Emirates',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'owner_name' => 'Owner',
            'owner_email' => 'admin@clinic.test',
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
        ])->assertSessionHasErrors('owner_email');

        $errors = session('errors')->get('owner_email');
        $this->assertSame(
            'Registration could not be completed. Please check your details and try again.',
            $errors[0],
        );

        $this->assertDatabaseMissing('clinics', ['code' => 'DUP_EMAIL']);
    }

    public function test_successful_clinic_registration_is_audited(): void
    {
        $this->post(route('register-clinic.store'), [
            'clinic_name' => 'Audit Clinic',
            'clinic_code' => 'AUDIT_CLINIC',
            'country' => 'United Arab Emirates',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'owner_name' => 'Audit Owner',
            'owner_email' => 'owner@audit-clinic.test',
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
        ])->assertRedirect(route('configuration.dashboard'));

        $clinic = Clinic::query()->where('code', 'AUDIT_CLINIC')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ClinicRegistered->value,
            'clinic_id' => $clinic->id,
        ]);
    }

    public function test_deactivated_account_returns_generic_invalid_credentials_message(): void
    {
        $user = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();
        $user->is_active = false;
        $user->save();

        $this->post(route('login'), [
            'email' => 'viewer@clinic.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $errors = session('errors')->get('email');
        $this->assertSame(AuthenticationService::INVALID_CREDENTIALS_MESSAGE, $errors[0]);
    }
}
