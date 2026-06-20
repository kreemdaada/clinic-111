<?php

namespace App\Models;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyReport extends Model
{
    protected $fillable = [
        'report_date',
        'source_type',
        'source_file_name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'source_type' => ReportSourceType::class,
            'status' => ReportStatus::class,
        ];
    }

    public function dailyWorkRows(): HasMany
    {
        return $this->hasMany(DailyWorkRow::class);
    }

    public function isApproved(): bool
    {
        return $this->status === ReportStatus::Approved;
    }
}
