<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Shared password policy for registration and admin user management (ADR-032).
 */
final class SecurePassword
{
    public static function rule(): Password
    {
        return Password::defaults();
    }

    /**
     * Strong password for tests and documentation examples.
     */
    public static function example(): string
    {
        return 'SecurePass1!';
    }
}
