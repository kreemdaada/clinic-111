<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkItem extends Model
{
    protected $fillable = [
        'daily_work_row_id',
        'treatment_id',
        'quantity',
        'confidence',
        'warning_message',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'confidence' => 'integer',
        ];
    }

    public function dailyWorkRow(): BelongsTo
    {
        return $this->belongsTo(DailyWorkRow::class);
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function labJob(): HasOne
    {
        return $this->hasOne(LabJob::class);
    }
}
