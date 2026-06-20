<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'daily_work_row_id',
        'payment_method',
        'amount',
        'currency',
        'exchange_rate',
        'amount_aed',
        'paid_at',
    ];

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

    public function dailyWorkRow(): BelongsTo
    {
        return $this->belongsTo(DailyWorkRow::class);
    }
}
