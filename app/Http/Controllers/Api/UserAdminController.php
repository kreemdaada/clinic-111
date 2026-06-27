<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ResetUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\Configuration\TenantResourceGuard;
use App\Services\User\UserManagementService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Admin API for user role management.
 */
class UserAdminController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
        private readonly TenantResourceGuard $tenantResourceGuard,
    ) {}

    public function index(): JsonResponse
    {
        $users = $this->userManagementService->listForAdministration()
            ->map(fn (User $user) => $this->formatUser($user));

        return response()->json(['data' => $users]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $result = $this->userManagementService->create($request->validated());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'User created.',
            'data' => $this->formatUser($result['user']),
            'temporary_password' => $result['temporary_password'],
        ], 201);
    }

    public function update(UpdateUserRequest $request, int $managedUser): JsonResponse
    {
        $account = $this->tenantResourceGuard->findAccessibleOrAbort(User::class, $managedUser);

        try {
            $account = $this->userManagementService->update($account, $request->validated(), $request->user());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'User updated.',
            'data' => $this->formatUser($account),
        ]);
    }

    public function destroy(int $managedUser): JsonResponse
    {
        $account = $this->tenantResourceGuard->findAccessibleOrAbort(User::class, $managedUser);

        try {
            $account = $this->userManagementService->deactivate($account, request()->user());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'User deactivated.',
            'data' => $this->formatUser($account),
        ]);
    }

    public function resetPassword(ResetUserPasswordRequest $request, int $managedUser): JsonResponse
    {
        $account = $this->tenantResourceGuard->findAccessibleOrAbort(User::class, $managedUser);

        try {
            $result = $this->userManagementService->resetPassword($account, $request->validated());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Password reset.',
            'data' => $this->formatUser($result['user']),
            'temporary_password' => $result['temporary_password'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ];
    }
}
