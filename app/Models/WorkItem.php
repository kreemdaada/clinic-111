<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One parsed treatment line (code + quantity) from `treatment_text`.
 *
 * Table: `work_items`. Only created for treatments with `has_lab_cost = true`
 * unless manually inserted for testing.
 */
class WorkItem extends Model
{
    protected $fillable = [
        'daily_work_row_id',
        'treatment_id',
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

    /**
     * Calculated lab job (JOB cost) for this work item, if any.
     */
    public function labJob(): HasOne
    {
        return $this->hasOne(LabJob::class);
    }
}
