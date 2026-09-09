<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Guest self-service password reset (ADR-040).
 *
 * Routes: GET/POST /forgot-password, GET/POST /reset-password/{token}
 */
class PasswordResetController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->validated('email')));

        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User && $user->is_active) {
            Password::sendResetLink(['email' => $email]);
        }

        $this->auditLogService->logPasswordResetRequested($email);

        return back()->with('status', __('passwords.sent'));
    }

    public function edit(string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => old('email', request()->query('email')),
        ]);
    }

    public function update(ResetPasswordRequest $request): RedirectResponse
    {
        $resetCompleted = false;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$resetCompleted): void {
                if (! $user->is_active) {
                    return;
                }

                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                $this->auditLogService->logPasswordReset($user);

                event(new PasswordReset($user));

                $resetCompleted = true;
            }
        );

        if ($status !== Password::PASSWORD_RESET || ! $resetCompleted) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status === Password::PASSWORD_RESET ? Password::INVALID_TOKEN : $status)]);
        }

        return redirect()
            ->route('login')
            ->with('success', __('passwords.reset'));
    }
}
