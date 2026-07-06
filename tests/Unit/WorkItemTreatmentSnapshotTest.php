<?php

namespace Tests\Unit;

use App\Enums\ReportSourceType;
use App\Models\Treatment;
use App\Services\Accounting\WorkItemPersistenceService;
use App\Services\Accounting\WorkItemTreatmentSnapshotBackfillService;
use App\Services\Configuration\OpgTreatmentProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkItemTreatmentSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_and_import_paths_use_same_snapshot_service(): void
    {
        $this->seedAccountingData();
        app(OpgTreatmentProvisioner::class)->provisionForClinic($this->clinic111());

        $treatment = Treatment::query()->where('code', 'OPG_NORMAL')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
        ]);
        $row = $this->createDailyWorkRow($report, [
            'doctor_id' => $this->clinic111()->doctors()->first()->id,
            'work_date' => '2026-06-03',
            'treatment_text' => 'OPG_NORMAL x 1',
        ]);

        $workItem = app(WorkItemPersistenceService::class)->create($row, $treatment, 1);

        $this->assertSame('OPG_NORMAL', $workItem->treatment_code_snapshot);
        $this->assertSame('200.00', (string) $workItem->treatment_price_aed);
        $this->assertSame('200.00', (string) $workItem->treatment_price_original);
    }

    public function test_backfill_is_idempotent_and_preserves_existing_snapshots(): void
    {
        $this->seedAccountingData();
        app(OpgTreatmentProvisioner::class)->provisionForClinic($this->clinic111());

        $treatment = Treatment::query()->where('code', 'OPG_NORMAL')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
        ]);
        $row = $this->createDailyWorkRow($report, [
            'doctor_id' => $this->clinic111()->doctors()->first()->id,
            'work_date' => '2026-06-03',
        ]);

        $workItem = $this->createWorkItem($row, [
            'treatment_id' => $treatment->id,
            'quantity' => 1,
        ]);

        $originalSnapshot = (string) $workItem->treatment_price_aed;
        $treatment->update(['treatment_price' => '999.00']);

        $this->assertSame(0, app(WorkItemTreatmentSnapshotBackfillService::class)->run());
        $this->assertSame($originalSnapshot, (string) $workItem->fresh()->treatment_price_aed);
        $this->assertSame(0, app(WorkItemTreatmentSnapshotBackfillService::class)->run());
    }

    public function test_snapshot_columns_exist_only_once_in_schema(): void
    {
        $columns = Schema::getColumnListing('work_items');

        foreach ([
            'treatment_code_snapshot',
            'treatment_price_original',
            'treatment_price_currency',
            'exchange_rate_to_aed',
            'treatment_price_aed',
        ] as $column) {
            $this->assertContains($column, $columns);
            $this->assertSame(1, count(array_filter($columns, fn (string $name) => $name === $column)));
        }
    }
}
