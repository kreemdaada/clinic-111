<?php

namespace App\Models;

use App\Domain\Currency\Currency;
use App\Domain\Currency\CurrencyCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Root tenant entity for independent clinic accounting (ADR-026).
 *
 * Table: `clinics`. Configuration models are scoped by `clinic_id` (Milestone 07).
 * Query isolation and runtime resolver arrive in Milestone 08.
 */
class Clinic extends Model
{
    protected $fillable = [
        'name',
        'code',
        'currency',
        'timezone',
        'country',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function labs(): HasMany
    {
        return $this->hasMany(Lab::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    public function labPrices(): HasMany
    {
        return $this->hasMany(LabPrice::class);
    }

    public function doctorFixedFees(): HasMany
    {
        return $this->hasMany(DoctorFixedFee::class);
    }

    public function baseCurrency(): Currency
    {
        return CurrencyCatalog::resolve($this->currency);
    }

    /**
     * @return array{code: string, name: string, symbol: string, precision: int}
     */
    public function currencyMetadata(): array
    {
        return $this->baseCurrency()->toArray();
    }
}
