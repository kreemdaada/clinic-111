<?php

namespace App\Enums;

/**
 * Origin of a {@see \App\Models\DailyReport} record.
 *
 * Stored in `daily_reports.source_type`. Determines whether rows came from
 * Excel import (V1) or manual web entry (V2 planned).
 */
enum ReportSourceType: string
{
    /** Daily report imported from an uploaded `.xlsx` / `.xlsm` file. */
    case ExcelUpload = 'excel_upload';

    /** Daily report created via web form without Excel (V2). */
    case ManualEntry = 'manual_entry';
}
