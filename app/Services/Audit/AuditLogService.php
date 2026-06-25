<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\DailyReport;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Persists structured audit log entries for sensitive accounting actions.
 */
class AuditLogService
{
    public function log(
        AuditAction $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
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
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
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
            'valid_from' => $labPrice->valid_from?->toDateString(),
            'valid_to' => $labPrice->valid_to?->toDateString(),
            'is_active' => $labPrice->is_active,
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
}
