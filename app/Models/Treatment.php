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

    protected $attributes = [
        'requires_nurse_commission' => false,
    ];

    protected $fillable = [
        'clinic_id',
        'code',
        'name',
        'description',
        'treatment_price',
        'treatment_price_currency',
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
            'requires_nurse_commission' => 'boolean',
            'treatment_price' => 'decimal:2',
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

    /**
     * Nurse commission rates configured for this treatment.
     */
    public function nurseCommissionRates(): HasMany
    {
        return $this->hasMany(NurseCommissionRate::class);
    }

    /**
     * Historical nurse commission snapshots for this treatment.
     */
    public function nurseCommissions(): HasMany
    {
        return $this->hasMany(NurseCommission::class);
    }
}
