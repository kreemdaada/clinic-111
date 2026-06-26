<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unit lab price for a treatment at a lab, optionally scoped to one doctor.
 *
 * Table: `lab_prices`. `doctor_id = null` means default price for all doctors at that lab.
 */
class LabPrice extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'lab_id',
        'treatment_id',
        'doctor_id',
        'unit_cost',
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
            'unit_cost' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Lab that charges this unit cost.
     */
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    /**
     * Treatment this price applies to (MC, ZIR, IMPL, etc.).
     */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    /**
     * Doctor-specific override; null when this is the default price row.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
