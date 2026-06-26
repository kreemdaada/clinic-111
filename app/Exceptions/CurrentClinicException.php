<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the current clinic cannot be resolved for an authenticated request.
 */
class CurrentClinicException extends RuntimeException
{
    public static function noAuthenticatedUser(): self
    {
        return new self('No authenticated user is available to resolve the current clinic.');
    }

    public static function userHasNoClinic(): self
    {
        return new self('The authenticated user is not assigned to a clinic.');
    }

    public static function clinicNotFound(int $clinicId): self
    {
        return new self("Clinic [{$clinicId}] was not found for the authenticated user.");
    }
}
