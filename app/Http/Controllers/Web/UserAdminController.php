<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\PreservesConfigurationReturn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ResetUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\Configuration\TenantResourceGuard;
use App\Services\User\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Admin-only user management (roles, activation, password reset).
 */
class UserAdminController extends Controller
{
    use PreservesConfigurationReturn;

    public function __construct(
        private readonly UserManagementService $userManagementService,
        private readonly TenantResourceGuard $tenantResourceGuard,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.users.index', [
            'users' => $this->userManagementService->listForAdministration(),
            'roles' => ['admin', 'accountant', 'viewer'],
            'showConfigurationBack' => $this->showConfigurationBack($request),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        try {
            $result = $this->userManagementService->create($request->validated());
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['create' => $exception->getMessage()]);
        }

        $message = $result['temporary_password'] !== null
            ? __('configuration.flash.user_created_with_password', ['password' => $result['temporary_password']])
            : __('configuration.flash.user_created');

        return redirect()
            ->route('admin.users.index', $this->mergeConfigurationReturn($request))
            ->with('success', $message);
    }

    public function update(UpdateUserRequest $request, int $managedUser): RedirectResponse
    {
        $account = $this->tenantResourceGuard->findAccessibleOrAbort(User::class, $managedUser);

        try {
            $this->userManagementService->update($account, $request->validated(), $request->user());
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['update' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.users.index', $this->mergeConfigurationReturn($request))
            ->with('success', __('configuration.flash.user_updated', ['email' => $account->email]));
    }

    public function destroy(Request $request, int $managedUser): RedirectResponse
    {
        $account = $this->tenantResourceGuard->findAccessibleOrAbort(User::class, $managedUser);

        try {
            $this->userManagementService->deactivate($account, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['deactivate' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.users.index', $this->mergeConfigurationReturn($request))
            ->with('success', __('configuration.flash.user_deleted', ['email' => $account->email]));
    }

    public function resetPassword(ResetUserPasswordRequest $request, int $managedUser): RedirectResponse
    {
        $account = $this->tenantResourceGuard->findAccessibleOrAbort(User::class, $managedUser);

        try {
            $result = $this->userManagementService->resetPassword($account, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['password' => $exception->getMessage()]);
        }

        $message = $result['temporary_password'] !== null
            ? __('configuration.flash.password_reset_with_password', ['password' => $result['temporary_password']])
            : __('configuration.flash.password_reset');

        return redirect()
            ->route('admin.users.index', $this->mergeConfigurationReturn($request))
            ->with('success', $message);
    }
}
