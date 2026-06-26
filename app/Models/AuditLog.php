<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Models\Concerns\BelongsToClinic;
use App\Models\Concerns\ImmutableClinicOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable audit trail entry for sensitive actions (imports, price changes, approvals).
 *
 * Table: `audit_logs`. Links optionally to any auditable model via polymorphic relation.
 */
class AuditLog extends Model
{
    use BelongsToClinic, ImmutableClinicOwnership;

    protected $fillable = [
        'clinic_id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    /**
     * Cast database columns to native PHP / enum types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * User who performed the action (null for system actions).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Polymorphic target model (e.g. DailyReport) that was affected.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
