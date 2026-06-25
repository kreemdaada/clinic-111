<?php

namespace Tests\Unit;

use App\Services\Import\ExcelDailyReportParser;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExcelDailyReportParserPaymentTest extends TestCase
{
    public function test_daily_subtotal_excludes_balance_tabby_and_cheque_from_patient_rows(): void
    {
        $filePath = $this->createClinicDaySheet([
            ['paid balance', null, 2800, null],
            ['paid balance', null, null, 1500],
            ['EXO x 1', 350, null, null],
        ]);

        $parser = app(ExcelDailyReportParser::class);
        $rows = $parser->parse($filePath, Carbon::parse('2026-06-01'));

        $this->assertCount(1, $rows);
        $this->assertSame(350.0, $rows[0]['dhs_amount']);
        $this->assertSame(1500.0, $rows[0]['visa_amount']);
        $this->assertSame(0.0, $rows[0]['tabby_amount'] ?? 0.0);
        $this->assertSame(0.0, $rows[0]['cheque_amount'] ?? 0.0);
    }

    public function test_daily_subtotal_excludes_balance_cheque_from_patient_rows(): void
    {
        $filePath = $this->createClinicDaySheet([
            ['Paid balalnce + cheque', null, 6000, 400],
            ['EXO x 1', null, null, 700],
            ['given back to patient + Paid balalnce', -200, null, 1900],
        ], chequeHeader: true);

        $parser = app(ExcelDailyReportParser::class);
        $rows = $parser->parse($filePath, Carbon::parse('2026-06-01'));

        $this->assertCount(1, $rows);
        $this->assertSame(-200.0, $rows[0]['dhs_amount']);
        $this->assertSame(3000.0, $rows[0]['visa_amount']);
        $this->assertSame(0.0, $rows[0]['cheque_amount'] ?? 0.0);
    }

    /**
     * @param  array<int, array{0: string, 1: int|float|null, 2: int|float|null, 3: int|float|null}>  $patients
     */
    private function createClinicDaySheet(array $patients, bool $chequeHeader = false): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('8');

        $sheet->setCellValue('G2', 'DR Jack');
        $sheet->setCellValue('A3', 'Date');
        $sheet->setCellValue('B3', 'Name');
        $sheet->setCellValue('G3', 'Treatment');
        $sheet->setCellValue('H3', 'Dhs');
        $sheet->setCellValue('I3', $chequeHeader ? 'Cheque' : 'Tabby');
        $sheet->setCellValue('J3', 'Visa');

        $rowIndex = 4;
        foreach ($patients as $index => $patient) {
            [$treatment, $dhs, $middleColumn, $visa] = $patient;
            $sheet->setCellValue('B' . $rowIndex, 'Patient ' . ($index + 1));
            $sheet->setCellValue('G' . $rowIndex, $treatment);

            if ($dhs !== null) {
                $sheet->setCellValue('H' . $rowIndex, $dhs);
            }

            if ($middleColumn !== null) {
                $sheet->setCellValue('I' . $rowIndex, $middleColumn);
            }

            if ($visa !== null) {
                $sheet->setCellValue('J' . $rowIndex, $visa);
            }

            $rowIndex++;
        }

        $subtotalRow = $rowIndex + 1;
        $sheet->setCellValue('H' . $subtotalRow, array_sum(array_map(
            fn (array $patient): float => (float) ($patient[1] ?? 0),
            $patients,
        )));
        $sheet->setCellValue('I' . $subtotalRow, array_sum(array_map(
            fn (array $patient): float => (float) ($patient[2] ?? 0),
            $patients,
        )));
        $sheet->setCellValue('J' . $subtotalRow, array_sum(array_map(
            fn (array $patient): float => (float) ($patient[3] ?? 0),
            $patients,
        )));

        $filePath = tempnam(sys_get_temp_dir(), 'clinic-day-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }
}
