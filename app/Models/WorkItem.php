<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Models\Concerns\ImmutableClinicOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One parsed treatment line (code + quantity) from `treatment_text`.
 *
 * Table: `work_items`. Created for every valid parsed treatment.
 * {@see LabJob} rows are only created when the treatment has `has_lab_cost = true`.
 */
class WorkItem extends Model
{
    use BelongsToClinic, ImmutableClinicOwnership;

    protected $fillable = [
        'clinic_id',
        'daily_work_row_id',
        'treatment_id',
        'treatment_code_snapshot',
        'treatment_price_original',
        'treatment_price_currency',
        'exchange_rate_to_aed',
        'treatment_price_aed',
        'nurse_id',
        'quantity',
        'confidence',
        'warning_message',
    ];

    /**
     * Cast database columns to native PHP types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'confidence' => 'integer',
            'treatment_price_original' => 'decimal:2',
            'exchange_rate_to_aed' => 'decimal:4',
            'treatment_price_aed' => 'decimal:2',
        ];
    }

    /**
     * Daily report row this work item was parsed from.
     */
    public function dailyWorkRow(): BelongsTo
    {
        return $this->belongsTo(DailyWorkRow::class);
    }

    /**
     * Master treatment definition (code, lab-cost flag).
     */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class);
    }

    /**
     * Calculated lab job (JOB cost) for this work item, if any.
     */
    public function labJob(): HasOne
    {
        return $this->hasOne(LabJob::class);
    }

    /**
     * Nurse commission snapshot for this work item, if any.
     */
    public function nurseCommission(): HasOne
    {
        return $this->hasOne(NurseCommission::class);
    }
}
