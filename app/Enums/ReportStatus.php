<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Uploaded = 'uploaded';
    case Parsed = 'parsed';
    case Calculated = 'calculated';
    case Approved = 'approved';
    case Failed = 'failed';
}
