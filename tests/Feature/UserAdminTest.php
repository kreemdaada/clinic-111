<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\SecurePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_viewer_cannot_access_user_admin(): void
    {
        $viewer = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAsRole('viewer');
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_accountant_cannot_access_user_admin(): void
    {
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $this->actingAs($accountant)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAsRole('accountant');
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Viewer',
                'email' => 'new-viewer@clinic.test',
                'role' => 'viewer',
                'password' => SecurePassword::example(),
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'new-viewer@clinic.test',
            'role' => UserRole::Viewer->value,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserCreated->value,
            'user_id' => $admin->id,
        ]);
    }

    private function createTestUser(string $email, UserRole $role): User
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

    public function test_admin_can_change_role(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user = $this->createTestUser('role-target@clinic.test', UserRole::Viewer);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Role Target',
                'email' => 'role-target@clinic.test',
                'role' => 'accountant',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame(UserRole::Accountant, $user->fresh()->role);
    }

    public function test_admin_can_deactivate_user(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user = $this->createTestUser('deactivate-me@clinic.test', UserRole::Viewer);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);
    }

    public function test_audit_log_is_created_on_role_change(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user = $this->createTestUser('audit-role@clinic.test', UserRole::Viewer);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Audit Role',
                'email' => 'audit-role@clinic.test',
                'role' => 'accountant',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UserRoleChanged->value,
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
            'user_id' => $admin->id,
        ]);
    }
}
