<?php

namespace App\Enums;

enum ReportSourceType: string
{
    case ExcelUpload = 'excel_upload';
    case ManualEntry = 'manual_entry';
}
