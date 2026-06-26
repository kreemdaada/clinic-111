<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ResetUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\User\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

/**
 * Admin-only user management (roles, activation, password reset).
 */
class UserAdminController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
    ) {}

    public function index(): View
    {
        return view('admin.users.index', [
            'users' => $this->userManagementService->listForAdministration(),
            'roles' => ['admin', 'accountant', 'viewer'],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        try {
            $result = $this->userManagementService->create($request->validated());
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['create' => $exception->getMessage()]);
        }

        $message = 'User created.';

        if ($result['temporary_password'] !== null) {
            $message .= ' Temporary password: '.$result['temporary_password'];
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', $message);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        try {
            $this->userManagementService->update($user, $request->validated(), $request->user());
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['update' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->email} updated.");
    }

    public function destroy(User $user): RedirectResponse
    {
        try {
            $this->userManagementService->deactivate($user, request()->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['deactivate' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->email} deleted.");
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        try {
            $result = $this->userManagementService->resetPassword($user, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['password' => $exception->getMessage()]);
        }

        $message = 'Password reset.';

        if ($result['temporary_password'] !== null) {
            $message .= ' Temporary password: '.$result['temporary_password'];
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', $message);
    }
}
