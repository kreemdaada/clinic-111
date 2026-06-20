<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function seedAccountingData(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    protected function actingAsRole(string $role): User
    {
        $email = match ($role) {
            'admin' => 'admin@clinic.test',
            'accountant' => 'accountant@clinic.test',
            default => 'viewer@clinic.test',
        };

        $user = User::query()->where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }
}
