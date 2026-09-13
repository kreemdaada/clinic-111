<?php

namespace Tests\Unit;

use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Treatment;
use App\Models\WorkItem;
use App\Services\Accounting\TreatmentParserService;
use App\Services\Accounting\FixedFeeIncomeCalculator;
use Tests\TestCase;

class FixedFeeIncomeCalculatorTest extends TestCase
{
    private FixedFeeIncomeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->authenticateAdmin();
        $this->calculator = app(FixedFeeIncomeCalculator::class);
    }

    public function test_impl_is_always_paid_in_aed(): void
    {
        $row = $this->makeWorkRow(usd: '500.00', dhs: '0.00');
        $fee = $this->fixedFeeFor('IMPL');
        $item = $this->makeWorkItem($row, 'IMPL', 1);

        $line = $this->calculator->calculateLine($row, $item, $fee);

        $this->assertSame('500.00', $line['payout_amount']);
        $this->assertSame('AED', $line['payout_currency']);
        $this->assertSame('500.00', $line['amount_aed']);
        $this->assertSame('impl', $line['export_bucket']);
    }

    public function test_bg_paid_in_usd_when_row_has_usd_cash(): void
    {
        $row = $this->makeWorkRow(usd: '400.00', dhs: '0.00');
        $fee = $this->fixedFeeFor('BG');
        $item = $this->makeWorkItem($row, 'BG', 1);

        $line = $this->calculator->calculateLine($row, $item, $fee);

        $this->assertSame('200.00', $line['payout_amount']);
        $this->assertSame('USD', $line['payout_currency']);
        $this->assertSame('730.00', $line['amount_aed']);
        $this->assertSame('bg', $line['export_bucket']);
    }

    public function test_bg_paid_in_aed_when_row_has_no_usd_cash(): void
    {
        $row = $this->makeWorkRow(usd: '0.00', dhs: '2000.00');
        $fee = $this->fixedFeeFor('BG');
        $item = $this->makeWorkItem($row, 'BG', 1);

        $line = $this->calculator->calculateLine($row, $item, $fee);

        $this->assertSame('730.00', $line['payout_amount']);
        $this->assertSame('AED', $line['payout_currency']);
        $this->assertSame('730.00', $line['amount_aed']);
        $this->assertNull($line['export_bucket']);
    }

    public function test_sinus_paid_in_aed_when_row_has_no_usd_cash(): void
    {
        $row = $this->makeWorkRow(usd: '0.00', dhs: '1500.00');
        $fee = $this->fixedFeeFor('SINUS');
        $item = $this->makeWorkItem($row, 'SINUS', 1);

        $line = $this->calculator->calculateLine($row, $item, $fee);

        $this->assertSame('1095.00', $line['payout_amount']);
        $this->assertSame('AED', $line['payout_currency']);
        $this->assertSame('1095.00', $line['amount_aed']);
        $this->assertNull($line['export_bucket']);
    }

    public function test_sinus_paid_in_usd_when_row_has_usd_cash(): void
    {
        $row = $this->makeWorkRow(usd: '100.00', dhs: '0.00');
        $fee = $this->fixedFeeFor('SINUS');
        $item = $this->makeWorkItem($row, 'SINUS', 1);

        $line = $this->calculator->calculateLine($row, $item, $fee);

        $this->assertSame('300.00', $line['payout_amount']);
        $this->assertSame('USD', $line['payout_currency']);
        $this->assertSame('1095.00', $line['amount_aed']);
        $this->assertSame('sinus', $line['export_bucket']);
    }

    public function test_bg_quantity_two_paid_in_usd(): void
    {
        $row = $this->makeWorkRow(usd: '800.00', dhs: '0.00');
        $fee = $this->fixedFeeFor('BG');
        $item = $this->makeWorkItem($row, 'BG', 2);

        $line = $this->calculator->calculateLine($row, $item, $fee);

        $this->assertSame('400.00', $line['payout_amount']);
        $this->assertSame('USD', $line['payout_currency']);
        $this->assertSame('1460.00', $line['amount_aed']);
        $this->assertSame('bg', $line['export_bucket']);
    }

    public function test_combined_treatments_usd_row(): void
    {
        $row = $this->makeWorkRow(usd: '1200.00', dhs: '0.00');

        $bg = $this->calculator->calculateLine($row, $this->makeWorkItem($row, 'BG', 2), $this->fixedFeeFor('BG'));
        $impl = $this->calculator->calculateLine($row, $this->makeWorkItem($row, 'IMPL', 1), $this->fixedFeeFor('IMPL'));
        $sinus = $this->calculator->calculateLine($row, $this->makeWorkItem($row, 'SINUS', 1), $this->fixedFeeFor('SINUS'));

        $this->assertSame('400.00', $bg['payout_amount']);
        $this->assertSame('USD', $bg['payout_currency']);
        $this->assertSame('500.00', $impl['payout_amount']);
        $this->assertSame('AED', $impl['payout_currency']);
        $this->assertSame('300.00', $sinus['payout_amount']);
        $this->assertSame('USD', $sinus['payout_currency']);
    }

    public function test_combined_treatments_aed_row(): void
    {
        $row = $this->makeWorkRow(usd: '0.00', dhs: '5000.00');

        $bg = $this->calculator->calculateLine($row, $this->makeWorkItem($row, 'BG', 2), $this->fixedFeeFor('BG'));
        $impl = $this->calculator->calculateLine($row, $this->makeWorkItem($row, 'IMPL', 1), $this->fixedFeeFor('IMPL'));
        $sinus = $this->calculator->calculateLine($row, $this->makeWorkItem($row, 'SINUS', 1), $this->fixedFeeFor('SINUS'));

        $this->assertSame('1460.00', $bg['payout_amount']);
        $this->assertSame('AED', $bg['payout_currency']);
        $this->assertSame('500.00', $impl['payout_amount']);
        $this->assertSame('AED', $impl['payout_currency']);
        $this->assertSame('1095.00', $sinus['payout_amount']);
        $this->assertSame('AED', $sinus['payout_currency']);
    }

    public function test_sinuc_alias_parses_to_sinus_work_item(): void
    {
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $dailyReport = $this->createDailyReport([
            'report_date' => '2026-01-15',
            'source_type' => 'manual_entry',
            'status' => 'parsed',
        ]);

        $row = $this->createDailyWorkRow($dailyReport, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-01-15',
            'usd_amount' => '500.00',
            'treatment_text' => 'SINUC x 1',
        ]);

        app(TreatmentParserService::class)->parseAndPersist($row);

        $this->assertSame(1, $row->workItems()->count());
        $this->assertSame('SINUS', $row->workItems()->first()->treatment->code);
    }

    private function fixedFeeFor(string $code): DoctorFixedFee
    {
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();

        return $doctor->doctorFixedFees()
            ->whereHas('treatment', fn ($q) => $q->where('code', $code))
            ->firstOrFail();
    }

    private function makeWorkRow(string $usd, string $dhs): DailyWorkRow
    {
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $dailyReport = $this->createDailyReport([
            'report_date' => '2026-01-15',
            'source_type' => 'manual_entry',
            'status' => 'parsed',
        ]);

        return $this->createDailyWorkRow($dailyReport, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-01-15',
            'usd_amount' => $usd,
            'dhs_amount' => $dhs,
        ]);
    }

    private function makeWorkItem(DailyWorkRow $row, string $code, int $qty): WorkItem
    {
        $treatment = Treatment::query()->where('code', $code)->firstOrFail();

        return $this->createWorkItem($row, [
            'treatment_id' => $treatment->id,
            'quantity' => $qty,
        ])->load('treatment');
    }
}
