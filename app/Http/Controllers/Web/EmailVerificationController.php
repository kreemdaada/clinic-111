<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Email verification for newly registered clinic owners (ADR-033).
 */
class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->intended(route('configuration.dashboard'));
        }

        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('configuration.dashboard'));
        }

        $request->fulfill();

        $this->auditLogService->logEmailVerified($request->user());

        return redirect()
            ->intended(route('configuration.dashboard'))
            ->with('success', 'Your email address has been verified.');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('configuration.dashboard'));
        }

        $request->user()->sendEmailVerificationNotification();

        $this->auditLogService->logEmailVerificationSent($request->user());

        return back()->with('status', 'verification-link-sent');
    }
}
