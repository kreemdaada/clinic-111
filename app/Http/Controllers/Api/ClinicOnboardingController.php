<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\RegisterClinicRequest;
use App\Services\Configuration\ClinicOnboardingService;
use Illuminate\Http\JsonResponse;

/**
 * Public clinic onboarding API (ADR-030).
 *
 * Route: POST /api/register-clinic
 */
class ClinicOnboardingController extends Controller
{
    public function __construct(
        private readonly ClinicOnboardingService $clinicOnboardingService,
    ) {}

    public function store(RegisterClinicRequest $request): JsonResponse
    {
        $result = $this->clinicOnboardingService->register($request->validated());

        $token = $result['owner']->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'clinic' => [
                'id' => $result['clinic']->id,
                'name' => $result['clinic']->name,
                'code' => $result['clinic']->code,
                'currency' => $result['clinic']->currency,
                'timezone' => $result['clinic']->timezone,
                'country' => $result['clinic']->country,
            ],
            'user' => [
                'id' => $result['owner']->id,
                'name' => $result['owner']->name,
                'email' => $result['owner']->email,
                'role' => $result['owner']->role->value,
                'email_verified' => $result['owner']->hasVerifiedEmail(),
            ],
        ], 201);
    }
}
