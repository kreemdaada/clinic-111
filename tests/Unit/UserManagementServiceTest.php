<?php

namespace Tests\Unit;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\User\UserManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class UserManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserManagementService $userManagementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->userManagementService = app(UserManagementService::class);
    }

    public function test_create_user_with_manual_password(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $this->actingAs($admin);

        $result = $this->userManagementService->create([
            'name' => 'Manual Password User',
            'email' => 'manual@clinic.test',
            'role' => UserRole::Viewer->value,
            'password' => 'password123',
        ]);

        $this->assertNull($result['temporary_password']);
        $this->assertTrue(Hash::check('password123', $result['user']->password));
        $this->assertDatabaseHas('users', [
            'email' => 'manual@clinic.test',
            'role' => UserRole::Viewer->value,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserCreated->value,
            'auditable_id' => $result['user']->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_create_user_with_temporary_password(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $this->actingAs($admin);

        $result = $this->userManagementService->create([
            'name' => 'Temp Password User',
            'email' => 'temp@clinic.test',
            'role' => UserRole::Accountant->value,
            'generate_temp_password' => true,
        ]);

        $this->assertNotNull($result['temporary_password']);
        $this->assertSame(12, strlen($result['temporary_password']));
        $this->assertTrue(Hash::check($result['temporary_password'], $result['user']->password));
    }

    public function test_create_user_requires_password_or_generation_flag(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Password is required unless generating a temporary password.');

        $this->userManagementService->create([
            'name' => 'Missing Password',
            'email' => 'missing@clinic.test',
            'role' => UserRole::Viewer->value,
        ]);
    }

    public function test_update_user_changes_role_and_writes_audit_log(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user = $this->makeUser('role-change@clinic.test', UserRole::Viewer);
        $this->actingAs($admin);

        $updated = $this->userManagementService->update($user, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => UserRole::Accountant->value,
            'is_active' => true,
        ], $admin);

        $this->assertSame(UserRole::Accountant, $updated->role);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserRoleChanged->value,
            'auditable_id' => $user->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_deactivate_user_sets_is_active_false_without_deleting_row(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user = $this->makeUser('deactivate@clinic.test', UserRole::Viewer);
        $this->actingAs($admin);

        $deactivated = $this->userManagementService->deactivate($user, $admin);

        $this->assertFalse($deactivated->is_active);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserDeactivated->value,
            'auditable_id' => $user->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_deactivate_user_revokes_api_tokens(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user = $this->makeUser('token-user@clinic.test', UserRole::Viewer);
        Sanctum::actingAs($user);
        $user->createToken('api-token');
        $tokenId = $user->tokens()->firstOrFail()->id;

        $this->actingAs($admin);
        $this->userManagementService->deactivate($user, $admin);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You cannot deactivate your own account.');

        $this->userManagementService->deactivate($admin, $admin);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You cannot change your own role.');

        $this->userManagementService->update($admin, [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Viewer->value,
            'is_active' => true,
        ], $admin);
    }

    public function test_reset_password_with_temporary_password(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user = $this->makeUser('reset@clinic.test', UserRole::Viewer);
        $this->actingAs($admin);

        $result = $this->userManagementService->resetPassword($user, [
            'generate_temp_password' => true,
        ]);

        $this->assertNotNull($result['temporary_password']);
        $this->assertTrue(Hash::check($result['temporary_password'], $result['user']->password));
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PasswordReset->value,
            'auditable_id' => $user->id,
            'user_id' => $admin->id,
        ]);
    }

    private function makeUser(string $email, UserRole $role): User
    {
        $user = new User;
        $user->fill([
            'name' => 'Test User',
            'email' => $email,
            'role' => $role,
            'clinic_id' => $this->clinic111()->id,
        ]);
        $user->password = 'password';
        $user->is_active = true;
        $user->save();

        return $user->fresh();
    }
}
