<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\RegisterClinicRequest;
use App\Services\Configuration\ClinicOnboardingService;
use App\Support\ClinicRegistrationOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Public clinic onboarding wizard (ADR-030).
 *
 * Routes: GET/POST /register-clinic
 */
class ClinicOnboardingController extends Controller
{
    public function __construct(
        private readonly ClinicOnboardingService $clinicOnboardingService,
    ) {}

    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('configuration.dashboard');
        }

        return view('onboarding.register-clinic', [
            'currencies' => ClinicRegistrationOptions::currencies(),
            'timezones' => ClinicRegistrationOptions::timezones(),
        ]);
    }

    public function store(RegisterClinicRequest $request): RedirectResponse
    {
        $result = $this->clinicOnboardingService->register($request->validated());

        Auth::login($result['owner']);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        $welcome = __('messages.onboarding.welcome', ['code' => $result['clinic']->code]);

        if ($result['owner']->hasVerifiedEmail()) {
            return redirect()
                ->route('imports.index')
                ->with('success', __('messages.onboarding.welcome_dashboard', ['welcome' => $welcome]));
        }

        return redirect()
            ->route('verification.notice')
            ->with('success', __('messages.onboarding.welcome_verify_email', ['welcome' => $welcome]));
    }
}
