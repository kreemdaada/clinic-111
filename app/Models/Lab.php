<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lab extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function labPrices(): HasMany
    {
        return $this->hasMany(LabPrice::class);
    }

    public function labJobs(): HasMany
    {
        return $this->hasMany(LabJob::class);
    }
}
