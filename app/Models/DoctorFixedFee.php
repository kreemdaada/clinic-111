<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fixed fee per treatment for doctors with `CommissionType::Fixed` (Dr Wa: IMPL, BG, SINUS only).
 *
 * Table: `doctor_fixed_fees`. BG/SINUS USD fees may pay out in USD or AED — see {@see WaelFixedFeeCalculator}.
 */
class DoctorFixedFee extends Model
{
    protected $fillable = [
        'doctor_id',
        'treatment_id',
        'fee_amount',
        'currency',
        'valid_from',
        'valid_to',
    ];

    /**
     * Cast database columns to native PHP types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /**
     * Doctor who receives this fixed fee.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Treatment the fee applies to (e.g. IMPL, BG, SINUS for Dr Wa).
     */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }
}
