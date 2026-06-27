<?php

namespace App\Services\Security;

use App\Contracts\Security\CaptchaVerifier;
use Illuminate\Http\Request;

/**
 * Facade for CAPTCHA verification — delegates to configured driver (ADR-033).
 */
class CaptchaVerificationService
{
    public function __construct(
        private readonly CaptchaVerifier $verifier,
    ) {}

    public function isEnabled(): bool
    {
        return $this->verifier->isEnabled();
    }

    public function verify(?string $token, Request $request): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        return $this->verifier->verify($token, $request);
    }
}
