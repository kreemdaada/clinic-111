<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One accounting row from a daily report (one patient visit / payment line).
 *
 * Table: `daily_work_rows`. Holds payments, raw Excel data, and parsed treatments.
 * `paid_total_aed` = DHS + USD→AED + VISA (collected payments, not treatment value).
 */
class DailyWorkRow extends Model
{
    protected $fillable = [
        'daily_report_id',
        'doctor_id',
        'work_date',
        'patient_reference_hash',
        'excel_row_number',
        'treatment_text',
        'total_cost',
        'discount_amount',
        'dhs_amount',
        'usd_amount',
        'usd_to_aed_amount',
        'visa_amount',
        'paid_total_aed',
        'balance_dhs',
        'balance_usd',
        'crown_count',
        'raw_data_json',
    ];

    /**
     * Cast database columns to native PHP types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'total_cost' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'dhs_amount' => 'decimal:2',
            'usd_amount' => 'decimal:2',
            'usd_to_aed_amount' => 'decimal:2',
            'visa_amount' => 'decimal:2',
            'paid_total_aed' => 'decimal:2',
            'balance_dhs' => 'decimal:2',
            'balance_usd' => 'decimal:2',
            'crown_count' => 'integer',
            'excel_row_number' => 'integer',
            'raw_data_json' => 'array',
        ];
    }

    /**
     * Parent report this row belongs to.
     */
    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    /**
     * Doctor who performed the work / receives attribution.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Parsed treatment lines (only lab-cost codes are persisted).
     */
    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    /**
     * Individual payment components (DHS, USD, VISA) for this row.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
