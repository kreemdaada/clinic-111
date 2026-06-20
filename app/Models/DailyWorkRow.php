<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyWorkRow extends Model
{
    protected $fillable = [
        'daily_report_id',
        'doctor_id',
        'work_date',
        'patient_name',
        'mrn',
        'file_number',
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
            'raw_data_json' => 'array',
        ];
    }

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
