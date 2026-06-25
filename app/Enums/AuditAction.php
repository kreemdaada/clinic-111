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

    /** A user manually corrected accounting data (payments, rows, etc.). */
    case ManualCorrection = 'manual_correction';

    /** A doctor master record was created. */
    case DoctorCreated = 'doctor_created';

    /** A doctor master record was updated. */
    case DoctorUpdated = 'doctor_updated';

    /** A doctor was deactivated (never physically deleted). */
    case DoctorDeactivated = 'doctor_deactivated';

    /** A lab price row was created. */
    case LabPriceCreated = 'lab_price_created';

    /** A lab price row was deactivated. */
    case LabPriceDeactivated = 'lab_price_deactivated';

    /** An approved or locked report was unlocked by an admin. */
    case ReportUnlocked = 'report_unlocked';

    /** A user account was created. */
    case UserCreated = 'user_created';

    /** A user role was changed. */
    case UserRoleChanged = 'user_role_changed';

    /** A user account was deactivated. */
    case UserDeactivated = 'user_deactivated';

    /** A user password was reset by an admin. */
    case PasswordReset = 'password_reset';
}
