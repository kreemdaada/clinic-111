<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Programmatic Excel fixtures for import pipeline characterization tests.
 *
 * Uses synthetic patient labels only — no real customer data.
 */
final class ImportExcelFixtureBuilder
{
    /**
     * Minimal Clinic 111 day sheet with one patient row and a daily subtotal.
     *
     * @param  array<string, float|int|string|null>  $payments  Keys: dhs, cheque, tabby, usd, visa, rub
     */
    public static function legacyClinic111Workbook(
        string $doctorLabel = 'DR Jack',
        string $treatmentText = 'ZIR x 2',
        int $sheetDay = 8,
        array $payments = [],
    ): string {
        $payments = array_merge([
            'dhs' => 100.0,
            'cheque' => 25.0,
            'tabby' => 10.0,
            'usd' => 10.0,
            'visa' => 50.0,
            'rub' => 0.0,
        ], $payments);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle((string) $sheetDay);

        $sheet->setCellValue('G2', $doctorLabel);
        $sheet->fromArray([
            ['Date', 'Name', 'MRN', 'File', 'Total Cost', 'Disc.', 'Treatment', 'Dhs', '$/Euro', 'Visa', 'Cheque', 'Tabby', 'RUB'],
        ], null, 'A3');

        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('G4', $treatmentText);
        $sheet->setCellValue('H4', $payments['dhs']);
        $sheet->setCellValue('I4', $payments['usd']);
        $sheet->setCellValue('J4', $payments['visa']);
        $sheet->setCellValue('K4', $payments['cheque']);
        $sheet->setCellValue('L4', $payments['tabby']);
        $sheet->setCellValue('M4', $payments['rub']);

        $sheet->setCellValue('H5', $payments['dhs']);
        $sheet->setCellValue('I5', $payments['usd']);
        $sheet->setCellValue('J5', $payments['visa']);
        $sheet->setCellValue('K5', $payments['cheque']);
        $sheet->setCellValue('L5', $payments['tabby']);
        $sheet->setCellValue('M5', $payments['rub']);

        return self::save($spreadsheet);
    }

    /**
     * Clinic 111 sheet where the doctor label cannot be resolved in the database.
     */
    public static function legacyClinic111UnknownDoctorWorkbook(): string
    {
        return self::legacyClinic111Workbook(
            doctorLabel: 'DR Unknown Person',
            treatmentText: 'CF x 1',
            payments: ['dhs' => 50.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
    }

    /**
     * Clinic 111 sheet with a CASH boundary row that should be skipped.
     */
    public static function legacyClinic111CashSkipWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('9');

        $sheet->setCellValue('G2', 'DR Jack');
        $sheet->fromArray([
            ['Date', 'Name', 'MRN', 'File', 'Total Cost', 'Disc.', 'Treatment', 'Dhs', '$/Euro', 'Visa', 'Cheque', 'Tabby', 'RUB'],
        ], null, 'A3');

        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('H4', 100);
        $sheet->setCellValue('H5', 100);

        $sheet->setCellValue('G6', 'CASH');
        $sheet->setCellValue('H6', 5000);

        return self::save($spreadsheet);
    }

    /**
     * Clinic 111 sheet with treatment but zero payments (lab without payment scenario).
     */
    public static function legacyClinic111ZeroPaymentWorkbook(): string
    {
        return self::legacyClinic111Workbook(
            treatmentText: 'ZIR x 1',
            payments: ['dhs' => 0.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
    }

    /**
     * Clinic 111 sheet with an unknown treatment code.
     */
    public static function legacyClinic111UnknownTreatmentWorkbook(): string
    {
        return self::legacyClinic111Workbook(
            treatmentText: 'NOTREAL x 1',
            payments: ['dhs' => 80.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0],
        );
    }

    /**
     * Generic single-sheet workbook for non-legacy clinics.
     *
     * @param  array<string, float|int|string|null>  $payments
     */
    public static function genericClinicWorkbook(
        string $doctorCode,
        string $treatmentText = 'CF x 1',
        string $workDate = '2026-07-08',
        array $payments = [],
    ): string {
        $payments = array_merge([
            'dhs' => 100.0,
            'usd' => 10.0,
            'visa' => 0.0,
            'cheque' => 0.0,
            'tabby' => 0.0,
        ], $payments);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import');

        $sheet->fromArray([
            ['Doctor', 'Date', 'Name', 'MRN', 'File', 'Treatment', 'DHS', 'USD', 'Visa', 'Cheque', 'Tabby'],
        ], null, 'A1');

        $sheet->fromArray([
            [
                $doctorCode,
                $workDate,
                'Patient B',
                'MRN-001',
                'FILE-001',
                $treatmentText,
                $payments['dhs'],
                $payments['usd'],
                $payments['visa'],
                $payments['cheque'],
                $payments['tabby'],
            ],
        ], null, 'A2');

        return self::save($spreadsheet);
    }

    /**
     * Clinic 111 workbook with two day sheets (8 and 9) for multi-row extraction log tests.
     */
    public static function legacyClinic111MultiDayWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet8 = $spreadsheet->getActiveSheet();
        $sheet8->setTitle('8');
        self::fillLegacyDaySheet($sheet8, 'DR Jack', 'CF x 1', ['dhs' => 80.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0]);

        $sheet9 = $spreadsheet->createSheet();
        $sheet9->setTitle('9');
        self::fillLegacyDaySheet($sheet9, 'DR Jack', 'CF x 1', ['dhs' => 90.0, 'cheque' => 0.0, 'tabby' => 0.0, 'usd' => 0.0, 'visa' => 0.0, 'rub' => 0.0]);

        return self::save($spreadsheet);
    }

    /**
     * @param  array<string, float|int|string|null>  $payments
     */
    private static function fillLegacyDaySheet(
        Worksheet $sheet,
        string $doctorLabel,
        string $treatmentText,
        array $payments,
    ): void {
        $sheet->setCellValue('G2', $doctorLabel);
        $sheet->fromArray([
            ['Date', 'Name', 'MRN', 'File', 'Total Cost', 'Disc.', 'Treatment', 'Dhs', '$/Euro', 'Visa', 'Cheque', 'Tabby', 'RUB'],
        ], null, 'A3');

        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('G4', $treatmentText);
        $sheet->setCellValue('H4', $payments['dhs']);
        $sheet->setCellValue('I4', $payments['usd']);
        $sheet->setCellValue('J4', $payments['visa']);
        $sheet->setCellValue('K4', $payments['cheque']);
        $sheet->setCellValue('L4', $payments['tabby']);
        $sheet->setCellValue('M4', $payments['rub']);

        $sheet->setCellValue('H5', $payments['dhs']);
        $sheet->setCellValue('I5', $payments['usd']);
        $sheet->setCellValue('J5', $payments['visa']);
        $sheet->setCellValue('K5', $payments['cheque']);
        $sheet->setCellValue('L5', $payments['tabby']);
        $sheet->setCellValue('M5', $payments['rub']);
    }

    private static function save(Spreadsheet $spreadsheet): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'import-fixture-').'.xlsx';
        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }
}
