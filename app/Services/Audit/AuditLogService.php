<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Persists structured audit log entries for sensitive accounting actions.
 *
 * Used by {@see \App\Services\Import\ImportActivityLogger} and future admin flows.
 */
class AuditLogService
{
    /**
     * Write one audit log row with optional polymorphic target and value snapshots.
     *
     * @param  AuditAction  $action  Action type (import, price change, approval, …).
     * @param  Model|null  $auditable  Related model (e.g. DailyReport).
     * @param  array<string, mixed>|null  $oldValues  Previous state JSON snapshot.
     * @param  array<string, mixed>|null  $newValues  New state JSON snapshot.
     * @return AuditLog Created database record.
     */
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
        $request = request();
        if ($request !== null) {
            $ipAddress = $request->ip();
        }

        return AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
        ]);
    }
}
