<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master treatment catalog (MC, ZIR, CF, RCT, …).
 *
 * Table: `treatments`. `has_lab_cost` determines JOB calculation and Income columns H–P.
 */
class Treatment extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'code',
        'name',
        'description',
    ];

    /**
     * Cast database columns to native PHP types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_lab_cost' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Parsed work items referencing this treatment across all reports.
     */
    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    /**
     * Lab unit prices configured for this treatment.
     */
    public function labPrices(): HasMany
    {
        return $this->hasMany(LabPrice::class);
    }

    /**
     * Fixed doctor fees tied to this treatment (Dr Wa).
     */
    public function doctorFixedFees(): HasMany
    {
        return $this->hasMany(DoctorFixedFee::class);
    }
}
