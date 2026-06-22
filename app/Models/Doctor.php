<?php

namespace App\Models;

use App\Enums\CommissionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Clinic doctor with commission rules and optional lab assignment.
 *
 * Table: `doctors`. Master data — commission and lab prices drive income calculation.
 */
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

    /**
     * Cast database columns to native PHP / enum types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commission_type' => CommissionType::class,
            'commission_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Default external lab used when calculating JOB costs for this doctor.
     */
    public function defaultLab(): BelongsTo
    {
        return $this->belongsTo(Lab::class, 'default_lab_id');
    }

    /**
     * All daily report rows attributed to this doctor.
     */
    public function dailyWorkRows(): HasMany
    {
        return $this->hasMany(DailyWorkRow::class);
    }

    /**
     * Doctor-specific lab price overrides (falls back to default when null doctor_id on price).
     */
    public function labPrices(): HasMany
    {
        return $this->hasMany(LabPrice::class);
    }

    /**
     * Fixed per-treatment fees (used when `commission_type` is `fixed`, e.g. Dr Wa).
     */
    public function doctorFixedFees(): HasMany
    {
        return $this->hasMany(DoctorFixedFee::class);
    }

    /**
     * Per-treatment lab JOB rules (`bill_lab_job` drives {@see LabBillingResolver}).
     */
    public function doctorLabBillings(): HasMany
    {
        return $this->hasMany(DoctorLabBilling::class);
    }

    /**
     * Server Income Excel layout (sheet name, column map, layout type).
     */
    public function incomeExportProfile(): HasOne
    {
        return $this->hasOne(DoctorIncomeExportProfile::class);
    }
}
