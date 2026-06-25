<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Default API users: admin, accountant, viewer (password: `password`).
 *
 * @see database/seeders/README.md
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@clinic.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'accountant@clinic.test'],
            [
                'name' => 'Accountant User',
                'password' => Hash::make('password'),
                'role' => UserRole::Accountant,
                'is_active' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'viewer@clinic.test'],
            [
                'name' => 'Viewer User',
                'password' => Hash::make('password'),
                'role' => UserRole::Viewer,
                'is_active' => true,
            ],
        );
    }
}
