<?php

namespace App\Enums;

enum AuditAction: string
{
    case ReportImport = 'report_import';
    case PriceChange = 'price_change';
    case CommissionChange = 'commission_change';
    case ReportApproval = 'report_approval';
    case ManualCorrection = 'manual_correction';
}
