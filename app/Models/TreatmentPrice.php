<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Database\Factories\TreatmentPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Patient/list price for a treatment at a clinic (ADR-039).
 *
 * Table: `treatment_prices`. Separate from `lab_prices.unit_cost`.
 */
class TreatmentPrice extends Model
{
    /** @use HasFactory<TreatmentPriceFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'treatment_id',
        'unit_price',
        'currency',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Treatment this list price applies to.
     */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }
}
