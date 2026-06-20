<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Treatment extends Model
{
    protected $fillable = [
        'code',
        'name',
        'has_lab_cost',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_lab_cost' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
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
