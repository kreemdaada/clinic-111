<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\Concerns\ResolvesDefaultClinic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Default API users: admin, accountant, viewer (password: `password`).
 *
 * @see database/seeders/README.md
 */
class UserSeeder extends Seeder
{
    use ResolvesDefaultClinic;

    public function run(): void
    {
        $clinic = $this->defaultClinic();

        User::query()->updateOrCreate(
            ['email' => 'admin@clinic.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
                'clinic_id' => $clinic->id,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'accountant@clinic.test'],
            [
                'name' => 'Accountant User',
                'password' => Hash::make('password'),
                'role' => UserRole::Accountant,
                'is_active' => true,
                'clinic_id' => $clinic->id,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'viewer@clinic.test'],
            [
                'name' => 'Viewer User',
                'password' => Hash::make('password'),
                'role' => UserRole::Viewer,
                'is_active' => true,
                'clinic_id' => $clinic->id,
                'email_verified_at' => now(),
            ],
        );
    }
}
