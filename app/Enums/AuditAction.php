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

    /** A lab price row was reactivated. */
    case LabPriceActivated = 'lab_price_activated';

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

    /** A laboratory master record was created. */
    case LabCreated = 'lab_created';

    /** A laboratory master record was updated. */
    case LabUpdated = 'lab_updated';

    /** A laboratory was deactivated (never physically deleted). */
    case LabDeactivated = 'lab_deactivated';

    /** A laboratory was reactivated. */
    case LabActivated = 'lab_activated';

    /** A treatment master record was created. */
    case TreatmentCreated = 'treatment_created';

    /** A treatment master record was updated. */
    case TreatmentUpdated = 'treatment_updated';

    /** A treatment was deactivated (never physically deleted). */
    case TreatmentDeactivated = 'treatment_deactivated';

    /** A treatment was reactivated. */
    case TreatmentActivated = 'treatment_activated';

    /** A doctor fixed fee row was created. */
    case DoctorFixedFeeCreated = 'doctor_fixed_fee_created';

    /** A doctor fixed fee row was deactivated. */
    case DoctorFixedFeeDeactivated = 'doctor_fixed_fee_deactivated';

    /** A doctor fixed fee row was reactivated. */
    case DoctorFixedFeeActivated = 'doctor_fixed_fee_activated';

    /** A clinic tenant record was created. */
    case ClinicCreated = 'clinic_created';

    /** A clinic tenant record was updated. */
    case ClinicUpdated = 'clinic_updated';

    /** A clinic was deactivated (never physically deleted). */
    case ClinicDeactivated = 'clinic_deactivated';

    /** A clinic was reactivated. */
    case ClinicActivated = 'clinic_activated';

    /** A user successfully authenticated. */
    case LoginSucceeded = 'login_succeeded';

    /** A failed login attempt (invalid credentials). */
    case LoginFailed = 'login_failed';

    /** Login blocked after too many failed attempts. */
    case LoginLockout = 'login_lockout';

    /** Public clinic registration completed successfully. */
    case ClinicRegistered = 'clinic_registered';
}
