<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Login brute-force protection using Laravel RateLimiter (ADR-032).
 */
class LoginThrottleService
{
    public function throttleKey(string $email, Request $request): string
    {
        $normalizedEmail = Str::transliterate(Str::lower(trim($email)));

        return 'login|'.$normalizedEmail.'|'.$request->ip().'|'.$this->userAgentFingerprint($request);
    }

    private function userAgentFingerprint(Request $request): string
    {
        $userAgent = $request->userAgent();

        if (! is_string($userAgent) || $userAgent === '') {
            return 'unknown';
        }

        return hash('sha256', $userAgent);
    }

    public function tooManyAttempts(string $key): bool
    {
        return RateLimiter::tooManyAttempts($key, $this->maxAttempts());
    }

    public function availableIn(string $key): int
    {
        return RateLimiter::availableIn($key);
    }

    public function hit(string $key): void
    {
        RateLimiter::hit($key, $this->decaySeconds());
    }

    public function clear(string $key): void
    {
        RateLimiter::clear($key);
    }

    public function maxAttempts(): int
    {
        return (int) config('auth_security.login.max_attempts', 5);
    }

    public function decaySeconds(): int
    {
        return (int) config('auth_security.login.decay_seconds', 300);
    }
}
