<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-doctor rule: whether a lab-backed treatment generates JOB (lab cost).
 *
 * Table: `doctor_lab_billings`. Absence of a row means no lab job for that pair.
 */
class DoctorLabBilling extends Model
{
    protected $fillable = [
        'doctor_id',
        'treatment_id',
        'bill_lab_job',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bill_lab_job' => 'boolean',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }
}
