<?php

namespace App\Services\Configuration;

use App\Exceptions\CurrentClinicException;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the active clinic for the current authenticated request (ADR-027).
 *
 * No fallback clinic. No query isolation — ownership assignment only.
 */
class CurrentClinicResolver
{
    public function resolve(): Clinic
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw CurrentClinicException::noAuthenticatedUser();
        }

        if ($user->clinic_id === null) {
            throw CurrentClinicException::userHasNoClinic();
        }

        $clinic = Clinic::query()->find($user->clinic_id);

        if ($clinic === null) {
            throw CurrentClinicException::clinicNotFound((int) $user->clinic_id);
        }

        return $clinic;
    }

    public function resolveId(): int
    {
        return $this->resolve()->id;
    }
}
