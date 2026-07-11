<?php

namespace App\Services\User;

use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;
use App\Services\Configuration\CurrentClinicResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Admin user management — never physically delete accounts.
 */
class UserManagementService
{
    use ScopesConfigurationQueries;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    public function listQuery(): Builder
    {
        return $this->forCurrentClinic(User::class)->orderBy('name');
    }

    /**
     * @return Collection<int, User>
     */
    public function listForAdministration(): Collection
    {
        return $this->listQuery()->get();
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     role: string,
     *     password?: string|null,
     *     generate_temp_password?: bool,
     *     is_active?: bool,
     * }  $data
     * @return array{user: User, temporary_password: string|null}
     */
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            [$password, $temporaryPassword] = $this->resolvePassword($data);

            $user = new User;
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
            ]);
            $user->clinic_id = $this->currentClinicId();
            $user->password = $password;
            $user->is_active = $data['is_active'] ?? true;
            $user->email_verified_at = now();
            $user->save();

            $this->auditLogService->logUserCreated($user);

            return [
                'user' => $user->fresh(),
                'temporary_password' => $temporaryPassword,
            ];
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     role: string,
     *     is_active?: bool,
     * }  $data
     */
    public function update(User $user, array $data, ?User $actingUser = null): User
    {
        $this->assertSameClinic($user);

        return DB::transaction(function () use ($user, $data, $actingUser) {
            $oldValues = $this->auditLogService->userSnapshot($user);

            if ($actingUser !== null && $actingUser->id === $user->id) {
                if (array_key_exists('is_active', $data) && ! (bool) $data['is_active']) {
                    throw new RuntimeException(__('messages.users.cannot_deactivate_self'));
                }

                if (($data['role'] ?? $user->role->value) !== $user->role->value) {
                    throw new RuntimeException(__('messages.users.cannot_change_own_role'));
                }
            }

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
            ]);

            if (array_key_exists('is_active', $data)) {
                $user->is_active = (bool) $data['is_active'];
            }

            $user->save();

            $freshUser = $user->fresh();
            $newValues = $this->auditLogService->userSnapshot($freshUser);
            $this->auditLogService->logUserUpdated($freshUser, $oldValues, $newValues);

            return $freshUser;
        });
    }

    public function deactivate(User $user, ?User $actingUser = null): User
    {
        $this->assertSameClinic($user);

        if ($actingUser !== null && $actingUser->id === $user->id) {
            throw new RuntimeException(__('messages.users.cannot_deactivate_self'));
        }

        return DB::transaction(function () use ($user) {
            $oldValues = $this->auditLogService->userSnapshot($user);

            $user->is_active = false;
            $user->save();
            $user->tokens()->delete();

            $this->auditLogService->logUserDeactivated($user->fresh(), $oldValues);

            return $user->fresh();
        });
    }

    /**
     * @param  array{
     *     password?: string|null,
     *     generate_temp_password?: bool,
     * }  $data
     * @return array{user: User, temporary_password: string|null}
     */
    public function resetPassword(User $user, array $data): array
    {
        $this->assertSameClinic($user);

        return DB::transaction(function () use ($user, $data) {
            [$password, $temporaryPassword] = $this->resolvePassword($data);

            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete();

            $this->auditLogService->logPasswordReset($user);

            return [
                'user' => $user->fresh(),
                'temporary_password' => $temporaryPassword,
            ];
        });
    }

    /**
     * @param  array{password?: string|null, generate_temp_password?: bool}  $data
     * @return array{0: string, 1: string|null}
     */
    private function resolvePassword(array $data): array
    {
        if (! empty($data['generate_temp_password'])) {
            $plain = Str::password(12);

            return [$plain, $plain];
        }

        $password = $data['password'] ?? null;

        if ($password === null || trim($password) === '') {
            throw new RuntimeException(__('messages.users.password_required'));
        }

        return [$password, null];
    }
}
