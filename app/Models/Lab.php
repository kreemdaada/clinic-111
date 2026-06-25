<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * External dental lab that supplies crowns, implants, etc.
 *
 * Table: `labs`. Linked to prices and calculated lab jobs (JOB column).
 */
class Lab extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];

    /**
     * Cast database columns to native PHP types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Unit prices charged by this lab per treatment (optionally per doctor).
     */
    public function labPrices(): HasMany
    {
        return $this->hasMany(LabPrice::class);
    }

    /**
     * Calculated lab cost records that reference this lab.
     */
    public function labJobs(): HasMany
    {
        return $this->hasMany(LabJob::class);
    }
}
