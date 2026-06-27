<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Credential verification with brute-force throttling and security audit logging (ADR-032).
 */
class AuthenticationService
{
    public const INVALID_CREDENTIALS_MESSAGE = 'The provided credentials are invalid.';

    public function __construct(
        private readonly LoginThrottleService $loginThrottle,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Verify credentials and return the authenticated user.
     *
     * @param  array{email: string, password: string}  $credentials
     *
     * @throws ValidationException When credentials are invalid or login is throttled.
     */
    public function authenticate(array $credentials, Request $request): User
    {
        $email = strtolower(trim($credentials['email']));
        $password = $credentials['password'];
        $throttleKey = $this->loginThrottle->throttleKey($email, $request);

        if ($this->loginThrottle->tooManyAttempts($throttleKey)) {
            $seconds = $this->loginThrottle->availableIn($throttleKey);

            $this->auditLogService->logLoginLockout($email);

            throw ValidationException::withMessages([
                'email' => [$this->lockoutMessage($seconds)],
            ])->status(429);
        }

        $user = User::query()->where('email', $email)->first();

        $passwordValid = $user !== null && Hash::check($password, $user->password);
        $accountActive = $user !== null && $user->is_active;

        if (! $passwordValid || ! $accountActive) {
            $this->loginThrottle->hit($throttleKey);
            $this->auditLogService->logLoginFailed($email);

            throw ValidationException::withMessages([
                'email' => [self::INVALID_CREDENTIALS_MESSAGE],
            ]);
        }

        $this->loginThrottle->clear($throttleKey);
        $this->auditLogService->logLoginSucceeded($user);

        return $user;
    }

    private function lockoutMessage(int $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);

        if ($minutes <= 1) {
            return 'Too many login attempts. Please try again in one minute.';
        }

        return "Too many login attempts. Please try again in {$minutes} minutes.";
    }
}
