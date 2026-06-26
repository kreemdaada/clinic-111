<?php

namespace App\Models;

use App\Enums\LabJobStatus;
use App\Models\Concerns\BelongsToClinic;
use App\Models\Concerns\ImmutableClinicOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Calculated lab cost (JOB) for one work item.
 *
 * Table: `lab_jobs`. `total_cost_aed` = quantity × unit_cost from resolved lab price.
 */
class LabJob extends Model
{
    use BelongsToClinic, ImmutableClinicOwnership;

    protected $fillable = [
        'clinic_id',
        'work_item_id',
        'lab_id',
        'lab_price_id',
        'quantity',
        'unit_cost',
        'total_cost_aed',
        'status',
    ];

    /**
     * Cast database columns to native PHP / enum types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'total_cost_aed' => 'decimal:2',
            'status' => LabJobStatus::class,
        ];
    }

    /**
     * Parsed treatment line that this lab cost was calculated from.
     */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    /**
     * Lab that supplied the unit price used in the calculation.
     */
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    /**
     * Exact price row (doctor override or default) used for `unit_cost`.
     */
    public function labPrice(): BelongsTo
    {
        return $this->belongsTo(LabPrice::class);
    }
}
