<?php

namespace App\Models;

use App\Enums\CommissionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    protected $fillable = [
        'name',
        'code',
        'commission_type',
        'commission_percentage',
        'default_lab_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'commission_type' => CommissionType::class,
            'commission_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function defaultLab(): BelongsTo
    {
        return $this->belongsTo(Lab::class, 'default_lab_id');
    }

    public function dailyWorkRows(): HasMany
    {
        return $this->hasMany(DailyWorkRow::class);
    }

    public function labPrices(): HasMany
    {
        return $this->hasMany(LabPrice::class);
    }

    public function doctorFixedFees(): HasMany
    {
        return $this->hasMany(DoctorFixedFee::class);
    }
}
