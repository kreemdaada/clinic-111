<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Database\Factories\NurseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * X-ray nurse master data for commission accounting (ADR-039).
 *
 * Table: `nurses`. No application login in v1 — deactivated via `is_active`.
 */
class Nurse extends Model
{
    /** @use HasFactory<NurseFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'code',
        'name',
        'is_active',
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
     * Commission percentage rows configured for this nurse.
     */
    public function nurseCommissionRates(): HasMany
    {
        return $this->hasMany(NurseCommissionRate::class);
    }

    /**
     * Historical commission snapshots referencing this nurse.
     */
    public function nurseCommissions(): HasMany
    {
        return $this->hasMany(NurseCommission::class);
    }
}
