<?php

namespace App\Services\DailyReport;

use App\Enums\ReportSourceType;
use App\Models\DailyReport;
use App\Services\Accounting\Concerns\ScopesAccountingQueries;
use App\Services\Configuration\CurrentClinicResolver;
use App\Support\AccountingScopedQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Clinic-scoped daily report reads for UI and API (ADR-029).
 */
class DailyReportQueryService
{
    use ScopesAccountingQueries;

    public function __construct(
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    public function listQuery(): Builder
    {
        return $this->forCurrentClinic(DailyReport::class);
    }

    /**
     * @return Collection<int, DailyReport>
     */
    public function listRecent(int $limit = 10): Collection
    {
        return $this->listQuery()
            ->withCount('dailyWorkRows')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, DailyReport>
     */
    public function listManualReports(): Collection
    {
        return $this->listQuery()
            ->where('source_type', ReportSourceType::ManualEntry)
            ->orderByDesc('report_date')
            ->get();
    }

    /**
     * @return Collection<int, DailyReport>
     */
    public function listManualReportsRecent(int $limit = 12): Collection
    {
        return $this->listQuery()
            ->where('source_type', ReportSourceType::ManualEntry)
            ->withCount('dailyWorkRows')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function assertAccessible(DailyReport $dailyReport): void
    {
        $this->assertSameClinic($dailyReport);
    }

    /**
     * @return Builder<\App\Models\DailyWorkRow>
     */
    public function workRowsQuery(DailyReport $dailyReport): Builder
    {
        $this->assertAccessible($dailyReport);

        return AccountingScopedQuery::workRows((int) $dailyReport->clinic_id, $dailyReport->id);
    }

    public function loadReportGraph(DailyReport $dailyReport): DailyReport
    {
        $workRows = $this->workRowsQuery($dailyReport)
            ->with([
                'doctor',
                'payments',
                'workItems.treatment',
                'workItems.labJob.lab',
            ])
            ->get();

        $dailyReport->setRelation('dailyWorkRows', $workRows);

        return $dailyReport;
    }
}
