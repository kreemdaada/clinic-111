<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Application user for web login and Sanctum API tokens.
 *
 * Table: `users`. Role drives route access via `EnsureUserHasRole` middleware.
 */
#[Fillable(['name', 'email', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Cast database columns to native PHP / enum types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Whether the user has full administrative access.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Whether the user can import daily reports and manage accounting data.
     */
    public function isAccountant(): bool
    {
        return $this->role === UserRole::Accountant;
    }

    /**
     * Whether the user has read-only access to reports and income summaries.
     */
    public function isViewer(): bool
    {
        return $this->role === UserRole::Viewer;
    }
}
