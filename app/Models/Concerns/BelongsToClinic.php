<?php

namespace App\Models\Concerns;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared clinic ownership for configuration models (Milestone 07).
 *
 * Transitional default: assigns CLINIC_111 on create when clinic_id is unset.
 * Replaced by CurrentClinicResolver in Milestone 08.
 */
trait BelongsToClinic
{
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public static function bootBelongsToClinic(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('clinic_id') === null) {
                $clinicId = Clinic::query()->where('code', 'CLINIC_111')->value('id');

                if ($clinicId !== null) {
                    $model->setAttribute('clinic_id', $clinicId);
                }
            }
        });
    }
}
