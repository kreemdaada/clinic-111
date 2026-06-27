<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\AuthenticationService;
use Illuminate\Http\JsonResponse;

/**
 * Sanctum token authentication for the REST API.
 *
 * Routes: POST /api/login, POST /api/logout (authenticated).
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Authenticate credentials and return a Bearer API token.
     *
     * @param  LoginRequest  $request  Validated email + password.
     * @return JsonResponse `{ token, user: { id, name, email, role } }`
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authenticationService->authenticate($request->validated(), $request);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ],
        ]);
    }

    /**
     * Revoke the current Sanctum access token for the authenticated user.
     *
     * @return JsonResponse `{ message: "Logged out successfully." }`
     */
    public function logout(): JsonResponse
    {
        $user = request()->user();

        if ($user !== null) {
            $accessToken = $user->currentAccessToken();

            if ($accessToken !== null) {
                $accessToken->delete();
            }

            $this->auditLogService->logLogout($user);
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
