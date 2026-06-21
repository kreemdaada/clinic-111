<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parser or lab-pricing warning raised during daily report import.
 *
 * Table: `daily_report_import_warnings`.
 */
class DailyReportImportWarning extends Model
{
    protected $fillable = [
        'daily_report_id',
        'daily_work_row_id',
        'excel_row_number',
        'doctor_code',
        'treatment_text',
        'warning_code',
        'message',
    ];

    /**
     * Parent report this warning belongs to.
     */
    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    /**
     * Work row that triggered the warning, when applicable.
     */
    public function dailyWorkRow(): BelongsTo
    {
        return $this->belongsTo(DailyWorkRow::class);
    }
}
