<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
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
