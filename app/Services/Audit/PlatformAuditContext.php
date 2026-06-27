<?php

namespace App\Services\Audit;

use App\Support\AuditContext;

/**
 * Resolves platform-scoped audit metadata (ADR-033).
 */
final class PlatformAuditContext
{
    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    public function tag(?array $values = null): ?array
    {
        if ($values === null) {
            return ['audit_context' => AuditContext::PLATFORM];
        }

        return array_merge($values, ['audit_context' => AuditContext::PLATFORM]);
    }
}
