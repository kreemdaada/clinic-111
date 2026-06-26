<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\RegisterClinicRequest;
use App\Services\Configuration\ClinicOnboardingService;
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

        return view('onboarding.register-clinic');
    }

    public function store(RegisterClinicRequest $request): RedirectResponse
    {
        $result = $this->clinicOnboardingService->register($request->validated());

        Auth::login($result['owner']);
        $request->session()->regenerate();

        return redirect()
            ->route('configuration.dashboard')
            ->with('success', "Welcome! Clinic {$result['clinic']->code} is ready to configure.");
    }
}
