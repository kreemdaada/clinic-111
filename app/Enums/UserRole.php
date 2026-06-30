<?php

namespace App\Enums;

use App\Models\User;

/**
 * Application role for {@see User} access control.
 *
 * Enforced by `EnsureUserHasRole` middleware on API and web routes.
 */
enum UserRole: string
{
    /** Full access: imports, master data, logs, approvals. */
    case Admin = 'admin';

    /** Can import daily reports and view accounting data. */
    case Accountant = 'accountant';

    /** Read-only access to reports and monthly income. */
    case Viewer = 'viewer';
}
