<?php

namespace App\Services\Security;

use App\Contracts\Security\CaptchaVerifier;
use Illuminate\Http\Request;

/**
 * Test/local CAPTCHA driver — accepts a known token when enabled.
 */
class FakeCaptchaVerifier implements CaptchaVerifier
{
    public function isEnabled(): bool
    {
        return (bool) config('auth_security.captcha.enabled', false);
    }

    public function verify(?string $token, Request $request): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $expected = (string) config('auth_security.captcha.fake_token', 'test-captcha-token');

        return is_string($token) && hash_equals($expected, $token);
    }
}
