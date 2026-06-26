<?php

namespace App\Services\Accounting\Concerns;

use App\Models\Clinic;
use App\Support\AccountingScopedQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Explicit clinic_id filtering for accounting reads (ADR-029).
 *
 * Requires the using class to expose CurrentClinicResolver as $currentClinicResolver.
 */
trait ScopesAccountingQueries
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

    protected function workRowsForReport(int $clinicId, int $dailyReportId): Builder
    {
        return AccountingScopedQuery::workRows($clinicId, $dailyReportId);
    }

    protected function paymentsForWorkRow(int $clinicId, int $dailyWorkRowId): Builder
    {
        return AccountingScopedQuery::payments($clinicId, $dailyWorkRowId);
    }

    protected function workItemsForWorkRow(int $clinicId, int $dailyWorkRowId): Builder
    {
        return AccountingScopedQuery::workItems($clinicId, $dailyWorkRowId);
    }

    protected function labJobsForWorkItem(int $clinicId, int $workItemId): Builder
    {
        return AccountingScopedQuery::labJobs($clinicId, $workItemId);
    }
}
