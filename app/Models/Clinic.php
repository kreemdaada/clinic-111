<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Root tenant entity for independent clinic accounting (ADR-026).
 *
 * Table: `clinics`. Future milestones will scope users, doctors, labs, and
 * accounting data through `clinic_id` — not used in Milestone 06.
 */
class Clinic extends Model
{
    protected $fillable = [
        'name',
        'code',
        'currency',
        'timezone',
        'country',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Future: users will belong to a clinic via `clinic_id`.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Future: doctors will belong to a clinic via `clinic_id`.
     */
    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    /**
     * Future: laboratories will belong to a clinic via `clinic_id`.
     */
    public function labs(): HasMany
    {
        return $this->hasMany(Lab::class);
    }
}
