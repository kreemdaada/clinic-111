<?php

namespace Tests\Unit;

use App\Services\Auth\LoginThrottleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginThrottleServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoginThrottleService $loginThrottle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginThrottle = app(LoginThrottleService::class);
    }

    public function test_throttle_key_is_stable_for_email_and_ip(): void
    {
        $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10']);

        $key = $this->loginThrottle->throttleKey('Admin@Clinic.Test', $request);

        $this->assertSame('login|admin@clinic.test|203.0.113.10', $key);
    }

    public function test_too_many_attempts_after_max_failures(): void
    {
        $key = 'login|test@example.test|127.0.0.1';
        RateLimiter::clear($key);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->loginThrottle->hit($key);
        }

        $this->assertTrue($this->loginThrottle->tooManyAttempts($key));
        $this->assertGreaterThan(0, $this->loginThrottle->availableIn($key));
    }

    public function test_clear_resets_attempt_counter(): void
    {
        $key = 'login|test@example.test|127.0.0.1';
        RateLimiter::clear($key);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->loginThrottle->hit($key);
        }

        $this->loginThrottle->clear($key);

        $this->assertFalse($this->loginThrottle->tooManyAttempts($key));
    }

    public function test_attempts_expire_after_decay_window(): void
    {
        $key = 'login|test@example.test|127.0.0.1';
        RateLimiter::clear($key);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->loginThrottle->hit($key);
        }

        $this->travel(301)->seconds();

        $this->assertFalse($this->loginThrottle->tooManyAttempts($key));
    }
}
