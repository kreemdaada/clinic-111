<?php

namespace App\Services\Configuration\Concerns;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Explicit clinic_id filtering for configuration reads (ADR-028).
 *
 * Requires the using class to expose CurrentClinicResolver as $currentClinicResolver.
 */
trait ScopesConfigurationQueries
{
    protected function currentClinicId(): int
    {
        return $this->currentClinicResolver->resolveId();
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function forCurrentClinic(string $modelClass): Builder
    {
        $query = $modelClass::query();

        if ($modelClass === Clinic::class) {
            return $query->where('id', $this->currentClinicId());
        }

        return $query->where('clinic_id', $this->currentClinicId());
    }

    protected function assertSameClinic(Model $model): void
    {
        if ($model instanceof Clinic) {
            if ((int) $model->id !== $this->currentClinicId()) {
                abort(404);
            }

            return;
        }

        if (! isset($model->clinic_id) || (int) $model->clinic_id !== $this->currentClinicId()) {
            abort(404);
        }
    }
}
