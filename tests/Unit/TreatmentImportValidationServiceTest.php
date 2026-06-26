<?php

namespace Tests\Unit;

use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Services\Import\TreatmentImportValidationService;
use Tests\TestCase;

class TreatmentImportValidationServiceTest extends TestCase
{
    private TreatmentImportValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->validationService = app(TreatmentImportValidationService::class);
    }

    public function test_valid_treatment_creates_work_item_without_warnings(): void
    {
        $row = $this->makeWorkRow('ZIR x 2 + POST x 1');

        $result = $this->validationService->validateAndPersist($row);

        $this->assertSame(2, $result->persistedItemCount);
        $this->assertSame([], $result->warnings);
        $this->assertSame(2, $row->workItems()->count());
    }

    public function test_invalid_format_emits_warning_and_skips_work_items(): void
    {
        $row = $this->makeWorkRow('zircon 2');

        $result = $this->validationService->validateAndPersist($row);

        $this->assertSame(0, $result->persistedItemCount);
        $this->assertCount(1, $result->warnings);
        $this->assertSame('invalid_format', $result->warnings[0]->warningCode);
        $this->assertStringContainsString('ZIR x 2', $result->warnings[0]->message);
        $this->assertSame(0, $row->workItems()->count());
    }

    public function test_unknown_code_emits_warning(): void
    {
        $row = $this->makeWorkRow('FOO x 2');

        $result = $this->validationService->validateAndPersist($row);

        $this->assertSame(0, $result->persistedItemCount);
        $this->assertCount(1, $result->warnings);
        $this->assertSame('unknown_treatment_code', $result->warnings[0]->warningCode);
    }

    public function test_missing_quantity_emits_warning(): void
    {
        $row = $this->makeWorkRow('ZIR');

        $result = $this->validationService->validateAndPersist($row);

        $this->assertSame(0, $result->persistedItemCount);
        $this->assertCount(1, $result->warnings);
        $this->assertSame('missing_quantity', $result->warnings[0]->warningCode);
    }

    public function test_partial_row_valid_items_persist_invalid_part_warns(): void
    {
        $row = $this->makeWorkRow('ZIR x 2 + zircon 2');

        $result = $this->validationService->validateAndPersist($row);

        $this->assertSame(1, $result->persistedItemCount);
        $this->assertCount(1, $result->warnings);
        $this->assertSame('invalid_format', $result->warnings[0]->warningCode);
        $this->assertSame(1, $row->workItems()->count());
    }

    public function test_remov_persists_as_work_item_with_lab_cost_flag(): void
    {
        $row = $this->makeWorkRow('REMOV x 2');

        $result = $this->validationService->validateAndPersist($row);

        $this->assertSame(1, $result->persistedItemCount);
        $this->assertSame([], $result->warnings);
        $this->assertSame(1, $row->workItems()->count());
        $this->assertTrue($row->workItems()->first()->treatment->has_lab_cost);
        $this->assertSame(2, $row->workItems()->first()->quantity);
    }

    private function makeWorkRow(string $treatmentText): DailyWorkRow
    {
        $doctor = Doctor::query()->where('code', 'RIYAD')->firstOrFail();

        $dailyReport = $this->createDailyReport([
            'report_date' => '2026-01-15',
            'source_type' => 'manual_entry',
            'status' => 'parsed',
        ]);

        return $this->createDailyWorkRow($dailyReport, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-01-15',
            'excel_row_number' => 25,
            'treatment_text' => $treatmentText,
        ]);
    }
}
