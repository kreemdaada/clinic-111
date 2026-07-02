<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Database\Factories\NurseCommissionRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nurse commission percentage for a nurse + treatment pair (ADR-039).
 *
 * Table: `nurse_commission_rates`. Mirrors `doctor_fixed_fees` lifecycle.
 */
class NurseCommissionRate extends Model
{
    /** @use HasFactory<NurseCommissionRateFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'nurse_id',
        'treatment_id',
        'commission_percentage',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commission_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Nurse who receives this commission rate.
     */
    public function nurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class);
    }

    /**
     * Treatment this commission rate applies to.
     */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }
}
