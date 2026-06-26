<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Prevents changing clinic_id after the record is created (ADR-029).
 *
 * Only new records receive clinic_id. No admin, service, or migration may reassign it at runtime.
 *
 * @mixin Model
 */
trait ImmutableClinicOwnership
{
    public static function bootImmutableClinicOwnership(): void
    {
        $dispatcher = Model::getEventDispatcher();

        if ($dispatcher === null) {
            return;
        }

        $dispatcher->listen(
            'eloquent.updating: '.static::class,
            function (Model $model): void {
                if (! $model->exists || ! $model->isDirty('clinic_id')) {
                    return;
                }

                throw new RuntimeException('clinic_id is immutable and cannot be changed after create.');
            },
        );
    }
}
