<?php

namespace App\Support;

/**
 * Audit log scope for tenant vs platform events (ADR-033).
 */
final class AuditContext
{
    public const PLATFORM = 'platform';

    public const TENANT = 'tenant';
}
