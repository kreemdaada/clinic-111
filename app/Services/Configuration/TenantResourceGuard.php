<?php

namespace App\Services\Configuration;

use App\Models\Clinic;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * Central tenant ownership guard for route-bound models (ADR-033, Milestone 13B).
 *
 * Cross-clinic access aborts with HTTP 404 — never 403.
 */
class TenantResourceGuard
{
    use ScopesConfigurationQueries;

    public function __construct(
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    public function assertAccessible(Model $model): void
    {
        $this->assertSameClinic($model);
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $modelClass
     * @return T
     */
    public function findAccessibleOrAbort(string $modelClass, int $id): Model
    {
        if ($modelClass === Clinic::class) {
            $model = Clinic::query()->find($id);

            if ($model === null || (int) $model->id !== $this->currentClinicId()) {
                abort(404);
            }

            return $model;
        }

        /** @var T $model */
        $model = $this->forCurrentClinic($modelClass)->whereKey($id)->firstOrFail();

        return $model;
    }
}
