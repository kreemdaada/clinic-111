<?php

namespace App\Models;

use App\Enums\LabJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabJob extends Model
{
    protected $fillable = [
        'work_item_id',
        'lab_id',
        'lab_price_id',
        'quantity',
        'unit_cost',
        'total_cost_aed',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'total_cost_aed' => 'decimal:2',
            'status' => LabJobStatus::class,
        ];
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function labPrice(): BelongsTo
    {
        return $this->belongsTo(LabPrice::class);
    }
}
