<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Models\Concerns\ImmutableClinicOwnership;
use Database\Factories\NurseCommissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historical nurse commission snapshot for one work item (ADR-039).
 *
 * Table: `nurse_commissions`. 1:1 with eligible `work_items`; immutable clinic ownership.
 */
class NurseCommission extends Model
{
    /** @use HasFactory<NurseCommissionFactory> */
    use BelongsToClinic, HasFactory, ImmutableClinicOwnership;

    protected $fillable = [
        'clinic_id',
        'work_item_id',
        'nurse_id',
        'nurse_name_snapshot',
        'treatment_id',
        'treatment_code_snapshot',
        'treatment_name_snapshot',
        'treatment_price_original',
        'treatment_price_currency',
        'exchange_rate_to_aed',
        'treatment_price_aed',
        'commission_percentage',
        'unit_commission_aed',
        'quantity',
        'total_commission_aed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'treatment_price_original' => 'decimal:2',
            'exchange_rate_to_aed' => 'decimal:4',
            'treatment_price_aed' => 'decimal:2',
            'commission_percentage' => 'decimal:2',
            'unit_commission_aed' => 'decimal:2',
            'quantity' => 'integer',
            'total_commission_aed' => 'decimal:2',
        ];
    }

    /**
     * Parsed treatment line this commission was calculated from.
     */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    /**
     * Nurse referenced at snapshot time.
     */
    public function nurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class);
    }

    /**
     * Treatment referenced at snapshot time.
     */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }
}
