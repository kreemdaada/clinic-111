<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabPrice extends Model
{
    protected $fillable = [
        'lab_id',
        'treatment_id',
        'doctor_id',
        'unit_cost',
        'currency',
        'valid_from',
        'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
