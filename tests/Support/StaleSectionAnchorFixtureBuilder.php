<?php

namespace Tests\Support;

use DateTime;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Anonymized Clinic 111 fixtures for stale-section anchor / template-year handling.
 */
final class StaleSectionAnchorFixtureBuilder
{
    /**
     * Tag 22 Jack: template year in anchor cell, low NF, valid section payments.
     */
    public static function tag22JackTemplateYearWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('22');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('A4', self::excelDate('2026-06-22'));
        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('D4', 'NF 5685');
        $sheet->setCellValue('G4', 'REMOV x 6');
        $sheet->setCellValue('H4', 1000);

        $sheet->setCellValue('B5', 'Patient B');
        $sheet->setCellValue('D5', 'NF 6089');
        $sheet->setCellValue('G5', 'MC x 4');

        $sheet->setCellValue('B6', 'Patient C');
        $sheet->setCellValue('D6', 'NF 5986');
        $sheet->setCellValue('G6', 'IMPLZIR x 4 + ABB x 4');
        $sheet->setCellValue('J6', 8400);

        $sheet->setCellValue('B7', 'Patient D');
        $sheet->setCellValue('D7', 'NF 5980');
        $sheet->setCellValue('G7', 'CF x 1');
        $sheet->setCellValue('J7', 350);

        $sheet->setCellValue('G8', 'PAID BALANCE');
        $sheet->setCellValue('J8', 700);

        $sheet->setCellValue('B9', 'Patient E');
        $sheet->setCellValue('D9', 'NF 6092');
        $sheet->setCellValue('G9', 'POST X 2');
        $sheet->setCellValue('H9', 500);

        $sheet->setCellValue('H10', 1500);
        $sheet->setCellValue('J10', 9450);

        return self::save($spreadsheet);
    }

    /**
     * Tag 22 Pouria: template year anchor, single patient, DHS subtotal.
     */
    public static function tag22PouriaTemplateYearWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('22');

        $sheet->setCellValue('G2', 'DR Pouria');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('A4', self::excelDate('2026-06-22'));
        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('D4', 'NF 6089');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('H4', 350);

        $sheet->setCellValue('H5', 350);

        return self::save($spreadsheet);
    }

    /**
     * Tag 22 Riyadh control: no NF numbers, already imported before the fix.
     */
    public static function tag22RiyadhControlWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('22');

        $sheet->setCellValue('G2', 'Dr. Riyadh');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('A4', self::excelDate('2026-06-22'));
        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('J4', 350);

        $sheet->setCellValue('B5', 'Patient B');
        $sheet->setCellValue('G5', 'PAID BALANCE');
        $sheet->setCellValue('H5', 500);

        $sheet->setCellValue('B6', 'Patient C');
        $sheet->setCellValue('G6', 'RCF x 1 + POST x 1');
        $sheet->setCellValue('H6', 500);

        $sheet->setCellValue('B7', 'Patient D');
        $sheet->setCellValue('G7', 'ZIR x 1');
        $sheet->setCellValue('H7', 10000);

        $sheet->setCellValue('B8', 'Patient E');
        $sheet->setCellValue('G8', 'CF x 1');
        $sheet->setCellValue('H8', 250);

        $sheet->setCellValue('H9', 11250);
        $sheet->setCellValue('J9', 350);

        return self::save($spreadsheet);
    }

    /**
     * Anchor month/day do not match the sheet day in the report month — truly stale.
     */
    public static function mismatchedAnchorMonthDayWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('22');

        $sheet->setCellValue('G2', 'DR Alpha');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('A4', self::excelDate('2025-03-15'));
        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('D4', 'NF 5000');
        $sheet->setCellValue('G4', 'EXO x 1');
        $sheet->setCellValue('H4', 400);

        $sheet->setCellValue('H5', 400);

        return self::save($spreadsheet);
    }

    /**
     * Current-year anchor on the same sheet day — must remain imported.
     */
    public static function currentYearAnchorWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('22');

        $sheet->setCellValue('G2', 'DR Beta');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('A4', self::excelDate('2027-06-22'));
        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('D4', 'NF 5000');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('H4', 250);

        $sheet->setCellValue('H5', 250);

        return self::save($spreadsheet);
    }

    /**
     * Template year with matching month/day but no report month context.
     */
    public static function templateYearWithoutReportMonthWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('22');

        $sheet->setCellValue('G2', 'DR Gamma');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('A4', self::excelDate('2026-06-22'));
        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('D4', 'NF 5000');
        $sheet->setCellValue('G4', 'RCT x 1');
        $sheet->setCellValue('H4', 300);

        $sheet->setCellValue('H5', 300);

        return self::save($spreadsheet);
    }

    /**
     * Unparseable anchor date must not throw and must not block import.
     */
    public static function unparseableAnchorDateWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('22');

        $sheet->setCellValue('G2', 'DR Delta');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('A4', 'not-a-date');
        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('D4', 'NF 5000');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('H4', 180);

        $sheet->setCellValue('H5', 180);

        return self::save($spreadsheet);
    }

    private static function excelDate(string $isoDate): float
    {
        return Date::PHPToExcel(new DateTime($isoDate));
    }

    private static function writeClinicHeaderRow(Worksheet $sheet, int $row): void
    {
        $sheet->fromArray([
            ['Date', 'Name', 'MRN', 'File', 'Total Cost', 'Disc.', 'Treatment', 'Dhs', '$/Euro', 'Visa', 'crown'],
        ], null, 'A'.$row);
    }

    private static function save(Spreadsheet $spreadsheet): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'stale-section-anchor-').'.xlsx';
        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }
}
