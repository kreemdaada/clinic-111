<?php

namespace App\Models;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One imported or manually entered daily accounting report (typically one calendar month).
 *
 * Table: `daily_reports`. Container for all `daily_work_rows` from a single Excel file or form session.
 */
class DailyReport extends Model
{
    protected $fillable = [
        'report_date',
        'source_type',
        'source_file_name',
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
            'report_date' => 'date',
            'source_type' => ReportSourceType::class,
            'status' => ReportStatus::class,
        ];
    }

    /**
     * Individual patient/payment rows extracted from the report.
     */
    public function dailyWorkRows(): HasMany
    {
        return $this->hasMany(DailyWorkRow::class);
    }

    /**
     * Whether this report is locked and cannot be re-imported or modified.
     */
    public function isApproved(): bool
    {
        return $this->status === ReportStatus::Approved;
    }
}
