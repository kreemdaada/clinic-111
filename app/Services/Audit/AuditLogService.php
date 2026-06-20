<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
