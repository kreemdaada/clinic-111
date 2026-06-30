<?php

namespace App\Models\Concerns;

use App\Models\Clinic;
use App\Services\Configuration\CurrentClinicResolver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared clinic ownership for configuration models (Milestone 07+).
 *
 * Clinic assignment on create is handled by configuration services via
 * {@see CurrentClinicResolver} (ADR-027).
 */
trait BelongsToClinic
{
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
