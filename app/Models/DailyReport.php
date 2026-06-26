<?php

namespace App\Models;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\Concerns\BelongsToClinic;
use App\Models\Concerns\ImmutableClinicOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One imported or manually entered daily accounting report (typically one calendar month).
 *
 * Table: `daily_reports`. Container for all `daily_work_rows` from a single Excel file or form session.
 */
class DailyReport extends Model
{
    use BelongsToClinic, ImmutableClinicOwnership;

    protected $fillable = [
        'clinic_id',
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
     * Parser and lab-pricing warnings raised during import.
     */
    public function importWarnings(): HasMany
    {
        return $this->hasMany(DailyReportImportWarning::class);
    }

    /**
     * Whether this report is approved and locked for editing.
     */
    public function isApproved(): bool
    {
        return $this->status === ReportStatus::Approved;
    }

    /**
     * Whether rows, payments, work items, and lab jobs cannot be edited.
     */
    public function isLocked(): bool
    {
        return in_array($this->status, [ReportStatus::Approved, ReportStatus::Locked], true);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }
}
