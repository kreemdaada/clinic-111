<?php

namespace App\Enums;

/**
 * Action type recorded in {@see \App\Models\AuditLog}.
 *
 * Used for traceability of imports, price changes, and manual corrections.
 */
enum AuditAction: string
{
    /** A daily Excel report was imported and calculated. */
    case ReportImport = 'report_import';

    /** A lab price or fee was changed in master data. */
    case PriceChange = 'price_change';

    /** A doctor commission rate or type was changed. */
    case CommissionChange = 'commission_change';

    /** A daily report was approved and locked. */
    case ReportApproval = 'report_approval';

    /** A user manually corrected accounting data (future use). */
    case ManualCorrection = 'manual_correction';
}
