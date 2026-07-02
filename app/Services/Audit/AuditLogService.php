<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\DailyReport;
use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Persists structured audit log entries for sensitive accounting actions.
 */
class AuditLogService
{
    public function __construct(
        private readonly PlatformAuditContext $platformAuditContext,
    ) {}

    public function log(
        AuditAction $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $clinicId = null,
        bool $platformContext = false,
    ): AuditLog {
        $auditableType = null;
        $auditableId = null;

        if ($auditable !== null) {
            $auditableType = $auditable->getMorphClass();
            $auditableId = $auditable->getKey();
        }

        $ipAddress = null;
        $userAgent = null;
        $request = request();

        if ($request !== null) {
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
        }

        return AuditLog::query()->create([
            'clinic_id' => $platformContext
                ? null
                : ($clinicId ?? $this->resolveClinicIdForAuditable($auditable)),
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $platformContext
                ? $this->platformAuditContext->tag($newValues)
                : $newValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function logDoctorCreated(Doctor $doctor): AuditLog
    {
        return $this->log(
            AuditAction::DoctorCreated,
            $doctor,
            null,
            $this->doctorSnapshot($doctor),
        );
    }

    public function logDoctorUpdated(Doctor $doctor, array $oldValues, array $newValues): AuditLog
    {
        $action = $this->commissionChanged($oldValues, $newValues)
            ? AuditAction::CommissionChange
            : AuditAction::DoctorUpdated;

        return $this->log($action, $doctor, $oldValues, $newValues);
    }

    public function logDoctorDeactivated(Doctor $doctor, array $oldValues): AuditLog
    {
        return $this->log(
            AuditAction::DoctorDeactivated,
            $doctor,
            $oldValues,
            $this->doctorSnapshot($doctor),
        );
    }

    public function logLabPriceCreated(LabPrice $labPrice): AuditLog
    {
        return $this->log(
            AuditAction::LabPriceCreated,
            $labPrice,
            null,
            $this->labPriceSnapshot($labPrice),
        );
    }

    public function logLabPriceUpdated(LabPrice $labPrice, array $oldValues, array $newValues): AuditLog
    {
        $action = ($oldValues['is_active'] ?? true) && ! ($newValues['is_active'] ?? true)
            ? AuditAction::LabPriceDeactivated
            : AuditAction::PriceChange;

        return $this->log($action, $labPrice, $oldValues, $newValues);
    }

    public function logLabPriceActivated(LabPrice $labPrice, array $oldValues): AuditLog
    {
        return $this->log(
            AuditAction::LabPriceActivated,
            $labPrice,
            $oldValues,
            $this->labPriceSnapshot($labPrice),
        );
    }

    public function logDoctorFixedFeeCreated(DoctorFixedFee $doctorFixedFee): AuditLog
    {
        return $this->log(
            AuditAction::DoctorFixedFeeCreated,
            $doctorFixedFee,
            null,
            $this->doctorFixedFeeSnapshot($doctorFixedFee),
        );
    }

    public function logDoctorFixedFeeUpdated(DoctorFixedFee $doctorFixedFee, array $oldValues, array $newValues): AuditLog
    {
        $action = ($oldValues['is_active'] ?? true) && ! ($newValues['is_active'] ?? true)
            ? AuditAction::DoctorFixedFeeDeactivated
            : AuditAction::PriceChange;

        return $this->log($action, $doctorFixedFee, $oldValues, $newValues);
    }

    public function logDoctorFixedFeeActivated(DoctorFixedFee $doctorFixedFee, array $oldValues): AuditLog
    {
        return $this->log(
            AuditAction::DoctorFixedFeeActivated,
            $doctorFixedFee,
            $oldValues,
            $this->doctorFixedFeeSnapshot($doctorFixedFee),
        );
    }

    public function logReportApproved(DailyReport $dailyReport, string $oldStatus): AuditLog
    {
        return $this->log(
            AuditAction::ReportApproval,
            $dailyReport,
            ['status' => $oldStatus],
            ['status' => $dailyReport->status->value],
        );
    }

    public function logReportUnlocked(DailyReport $dailyReport, string $oldStatus, string $reason): AuditLog
    {
        return $this->log(
            AuditAction::ReportUnlocked,
            $dailyReport,
            ['status' => $oldStatus],
            [
                'status' => $dailyReport->status->value,
                'unlock_reason' => $reason,
            ],
        );
    }

    public function logManualPaymentCorrection(DailyReport $dailyReport, array $oldValues, array $newValues): AuditLog
    {
        return $this->log(
            AuditAction::ManualCorrection,
            $dailyReport,
            $oldValues,
            $newValues,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function doctorSnapshot(Doctor $doctor): array
    {
        return [
            'name' => $doctor->name,
            'code' => $doctor->code,
            'commission_type' => $doctor->commission_type->value,
            'commission_percentage' => $doctor->commission_percentage !== null
                ? (string) $doctor->commission_percentage
                : null,
            'default_lab_id' => $doctor->default_lab_id,
            'is_active' => $doctor->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function labPriceSnapshot(LabPrice $labPrice): array
    {
        return [
            'lab_id' => $labPrice->lab_id,
            'treatment_id' => $labPrice->treatment_id,
            'doctor_id' => $labPrice->doctor_id,
            'unit_cost' => (string) $labPrice->unit_cost,
            'currency' => $labPrice->currency,
            'is_active' => $labPrice->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function doctorFixedFeeSnapshot(DoctorFixedFee $doctorFixedFee): array
    {
        return [
            'doctor_id' => $doctorFixedFee->doctor_id,
            'treatment_id' => $doctorFixedFee->treatment_id,
            'fee_amount' => (string) $doctorFixedFee->fee_amount,
            'currency' => $doctorFixedFee->currency,
            'valid_from' => $doctorFixedFee->valid_from?->toDateString(),
            'valid_to' => $doctorFixedFee->valid_to?->toDateString(),
            'is_active' => $doctorFixedFee->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function commissionChanged(array $oldValues, array $newValues): bool
    {
        return ($oldValues['commission_type'] ?? null) !== ($newValues['commission_type'] ?? null)
            || ($oldValues['commission_percentage'] ?? null) !== ($newValues['commission_percentage'] ?? null);
    }

    public function logUserCreated(User $user): AuditLog
    {
        return $this->log(
            AuditAction::UserCreated,
            $user,
            null,
            $this->userSnapshot($user),
        );
    }

    public function logUserUpdated(User $user, array $oldValues, array $newValues): ?AuditLog
    {
        if (($oldValues['role'] ?? null) !== ($newValues['role'] ?? null)) {
            return $this->log(AuditAction::UserRoleChanged, $user, $oldValues, $newValues);
        }

        if (($oldValues['is_active'] ?? true) && ! ($newValues['is_active'] ?? true)) {
            return $this->logUserDeactivated($user, $oldValues);
        }

        return null;
    }

    public function logUserDeactivated(User $user, array $oldValues): AuditLog
    {
        return $this->log(
            AuditAction::UserDeactivated,
            $user,
            $oldValues,
            $this->userSnapshot($user),
        );
    }

    public function logPasswordReset(User $user): AuditLog
    {
        return $this->log(
            AuditAction::PasswordReset,
            $user,
            null,
            ['email' => $user->email],
        );
    }

    public function logLoginSucceeded(User $user): AuditLog
    {
        return $this->log(
            AuditAction::LoginSucceeded,
            $user,
            null,
            ['email' => $user->email],
        );
    }

    public function logLoginFailed(string $email): AuditLog
    {
        $normalizedEmail = strtolower(trim($email));
        $user = User::query()->where('email', $normalizedEmail)->first();

        if ($user === null) {
            return $this->logPlatform(
                AuditAction::LoginFailed,
                ['email' => $normalizedEmail],
            );
        }

        return $this->log(
            AuditAction::LoginFailed,
            $user,
            null,
            ['email' => $normalizedEmail],
            (int) $user->clinic_id,
        );
    }

    public function logLoginLockout(string $email): AuditLog
    {
        $normalizedEmail = strtolower(trim($email));
        $user = User::query()->where('email', $normalizedEmail)->first();

        if ($user === null) {
            return $this->logPlatform(
                AuditAction::LoginLockout,
                ['email' => $normalizedEmail],
            );
        }

        return $this->log(
            AuditAction::LoginLockout,
            $user,
            null,
            ['email' => $normalizedEmail],
            (int) $user->clinic_id,
        );
    }

    public function logLogout(User $user): AuditLog
    {
        return $this->log(
            AuditAction::Logout,
            $user,
            null,
            ['email' => $user->email],
        );
    }

    public function logEmailVerificationSent(User $user): AuditLog
    {
        return $this->log(
            AuditAction::EmailVerificationSent,
            $user,
            null,
            ['email' => $user->email],
        );
    }

    public function logEmailVerified(User $user): AuditLog
    {
        return $this->log(
            AuditAction::EmailVerified,
            $user,
            null,
            ['email' => $user->email],
        );
    }

    public function logRegistrationAbuse(?string $ipAddress = null): AuditLog
    {
        return $this->logPlatform(
            AuditAction::RegistrationAbuse,
            ['ip_address' => $ipAddress ?? request()?->ip()],
        );
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    public function logPlatform(AuditAction $action, array $newValues = [], ?Model $auditable = null): AuditLog
    {
        return $this->log(
            $action,
            $auditable,
            null,
            $newValues,
            null,
            platformContext: true,
        );
    }

    public function logClinicRegistered(Clinic $clinic, User $owner): AuditLog
    {
        return $this->log(
            AuditAction::ClinicRegistered,
            $clinic,
            null,
            [
                'clinic_code' => $clinic->code,
                'owner_email' => $owner->email,
            ],
        );
    }

    public function logLabCreated(Lab $lab): AuditLog
    {
        return $this->log(
            AuditAction::LabCreated,
            $lab,
            null,
            $this->labSnapshot($lab),
        );
    }

    public function logLabUpdated(Lab $lab, array $oldValues, array $newValues): ?AuditLog
    {
        if (($oldValues['is_active'] ?? true) && ! ($newValues['is_active'] ?? true)) {
            return $this->logLabDeactivated($lab, $oldValues);
        }

        if (! ($oldValues['is_active'] ?? true) && ($newValues['is_active'] ?? true)) {
            return $this->log(
                AuditAction::LabActivated,
                $lab,
                $oldValues,
                $newValues,
            );
        }

        if ($oldValues === $newValues) {
            return null;
        }

        return $this->log(
            AuditAction::LabUpdated,
            $lab,
            $oldValues,
            $newValues,
        );
    }

    public function logLabDeactivated(Lab $lab, array $oldValues): AuditLog
    {
        return $this->log(
            AuditAction::LabDeactivated,
            $lab,
            $oldValues,
            $this->labSnapshot($lab),
        );
    }

    public function logTreatmentCreated(Treatment $treatment): AuditLog
    {
        return $this->log(
            AuditAction::TreatmentCreated,
            $treatment,
            null,
            $this->treatmentSnapshot($treatment),
        );
    }

    public function logTreatmentUpdated(Treatment $treatment, array $oldValues, array $newValues): ?AuditLog
    {
        if (($oldValues['is_active'] ?? true) && ! ($newValues['is_active'] ?? true)) {
            return $this->logTreatmentDeactivated($treatment, $oldValues);
        }

        if (! ($oldValues['is_active'] ?? true) && ($newValues['is_active'] ?? true)) {
            return $this->log(
                AuditAction::TreatmentActivated,
                $treatment,
                $oldValues,
                $newValues,
            );
        }

        if ($oldValues === $newValues) {
            return null;
        }

        return $this->log(
            AuditAction::TreatmentUpdated,
            $treatment,
            $oldValues,
            $newValues,
        );
    }

    public function logTreatmentDeactivated(Treatment $treatment, array $oldValues): AuditLog
    {
        return $this->log(
            AuditAction::TreatmentDeactivated,
            $treatment,
            $oldValues,
            $this->treatmentSnapshot($treatment),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function treatmentSnapshot(Treatment $treatment): array
    {
        return [
            'code' => $treatment->code,
            'name' => $treatment->name,
            'description' => $treatment->description,
            'has_lab_cost' => $treatment->has_lab_cost,
            'is_active' => $treatment->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function labSnapshot(Lab $lab): array
    {
        return [
            'name' => $lab->name,
            'code' => $lab->code,
            'is_active' => $lab->is_active,
        ];
    }

    public function logClinicCreated(Clinic $clinic): AuditLog
    {
        return $this->log(
            AuditAction::ClinicCreated,
            $clinic,
            null,
            $this->clinicSnapshot($clinic),
        );
    }

    public function logClinicUpdated(Clinic $clinic, array $oldValues, array $newValues): ?AuditLog
    {
        if (($oldValues['is_active'] ?? true) && ! ($newValues['is_active'] ?? true)) {
            return $this->logClinicDeactivated($clinic, $oldValues);
        }

        if (! ($oldValues['is_active'] ?? true) && ($newValues['is_active'] ?? true)) {
            return $this->log(
                AuditAction::ClinicActivated,
                $clinic,
                $oldValues,
                $newValues,
            );
        }

        if ($oldValues === $newValues) {
            return null;
        }

        return $this->log(
            AuditAction::ClinicUpdated,
            $clinic,
            $oldValues,
            $newValues,
        );
    }

    public function logClinicDeactivated(Clinic $clinic, array $oldValues): AuditLog
    {
        return $this->log(
            AuditAction::ClinicDeactivated,
            $clinic,
            $oldValues,
            $this->clinicSnapshot($clinic),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function clinicSnapshot(Clinic $clinic): array
    {
        return [
            'name' => $clinic->name,
            'code' => $clinic->code,
            'currency' => $clinic->currency,
            'timezone' => $clinic->timezone,
            'country' => $clinic->country,
            'is_active' => $clinic->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function userSnapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ];
    }

    private function resolveClinicIdForAuditable(?Model $auditable): int
    {
        if ($auditable instanceof Clinic) {
            return (int) $auditable->id;
        }

        if ($auditable instanceof User && $auditable->clinic_id !== null) {
            return (int) $auditable->clinic_id;
        }

        if ($auditable !== null && isset($auditable->clinic_id)) {
            return (int) $auditable->clinic_id;
        }

        $user = Auth::user();

        if ($user instanceof User && $user->clinic_id !== null) {
            return (int) $user->clinic_id;
        }

        throw new \RuntimeException('Unable to resolve clinic_id for tenant audit log entry.');
    }
}
