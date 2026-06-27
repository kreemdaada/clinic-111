<?php

namespace App\Contracts\Security;

use Illuminate\Http\Request;

/**
 * CAPTCHA / Turnstile verification contract (ADR-033).
 */
interface CaptchaVerifier
{
    public function isEnabled(): bool;

    public function verify(?string $token, Request $request): bool;
}
