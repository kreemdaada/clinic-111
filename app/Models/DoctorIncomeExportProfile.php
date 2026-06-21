<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Excel Server Income export layout for one doctor (sheet name, columns, layout type).
 *
 * Single source of truth for export + {@see \App\Support\IncomeSheetColumnMap} diagnostics.
 * Calculation still uses {@see \App\Services\Accounting\LabPriceResolver} (lab_prices table).
 */
class DoctorIncomeExportProfile extends Model
{
    protected $fillable = [
        'doctor_id',
        'sheet_name',
        'layout',
        'first_day_row',
        'write_payment_headers',
        'summary_shows_net_total',
        'payment_columns',
        'treatment_columns',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_day_row' => 'integer',
            'write_payment_headers' => 'boolean',
            'summary_shows_net_total' => 'boolean',
            'payment_columns' => 'array',
            'treatment_columns' => 'array',
        ];
    }

    /**
     * Doctor this export profile belongs to.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
