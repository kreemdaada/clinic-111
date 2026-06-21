<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum token authentication for the REST API.
 *
 * Routes: POST /api/login, POST /api/logout (authenticated).
 */
class AuthController extends Controller
{
    /**
     * Authenticate credentials and return a Bearer API token.
     *
     * @param  LoginRequest  $request  Validated email + password.
     * @return JsonResponse `{ token, user: { id, name, email, role } }`
     *
     * @throws ValidationException When email/password do not match.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

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
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
