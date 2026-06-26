<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToClinic;
use App\Models\Concerns\ImmutableClinicOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment component (DHS, USD, or VISA) for a daily work row.
 *
 * Table: `payments`. Non-zero components are stored separately; `amount_aed` is normalized for totals.
 */
class Payment extends Model
{
    use BelongsToClinic, ImmutableClinicOwnership;

    protected $fillable = [
        'clinic_id',
        'daily_work_row_id',
        'payment_method',
        'amount',
        'currency',
        'exchange_rate',
        'amount_aed',
        'paid_at',
    ];

    /**
     * Cast database columns to native PHP / enum types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'amount_aed' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /**
     * Daily report row this payment belongs to.
     */
    public function dailyWorkRow(): BelongsTo
    {
        return $this->belongsTo(DailyWorkRow::class);
    }
}
