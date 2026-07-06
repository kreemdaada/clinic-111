<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Anonymized Clinic 111 fixtures for nameless treatment / activity rows.
 */
final class NamelessTreatmentRowsFixtureBuilder
{
    /**
     * Tag 26 pattern: nameless activity rows followed by one closing subtotal line.
     */
    public static function tag26NamelessActivityRowsWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('26');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('G4', 'PAID BALANCE');
        $sheet->setCellValue('J4', 13800);

        $sheet->setCellValue('G5', 'REMOV x 2');
        $sheet->setCellValue('H5', 200);

        $sheet->setCellValue('G6', 'PAID BALANCE');
        $sheet->setCellValue('H6', 2600);

        $sheet->setCellValue('G7', 'RCF x 1');
        $sheet->setCellValue('J7', 350);

        $sheet->setCellValue('G8', 'MC x 2');
        $sheet->setCellValue('H8', 1500);

        $sheet->setCellValue('G9', 'TRANSFER FROM DR POURIA');
        $sheet->setCellValue('H9', 350);

        $sheet->setCellValue('H11', 4650);
        $sheet->setCellValue('J11', 14150);

        return self::save($spreadsheet);
    }

    /**
     * Single nameless activity row with payment inside an otherwise normal section.
     */
    public static function singleNamelessActivityWithPaymentWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('8');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('H4', 100);

        $sheet->setCellValue('G5', 'PAID BALANCE');
        $sheet->setCellValue('J5', 500);

        $sheet->setCellValue('H6', 100);
        $sheet->setCellValue('J6', 500);

        return self::save($spreadsheet);
    }

    /**
     * Nameless activity without payment, closed by a normal subtotal row.
     */
    public static function namelessActivityWithoutPaymentWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('9');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('G4', 'POST X 2');

        $sheet->setCellValue('B5', 'Patient B');
        $sheet->setCellValue('G5', 'EXO x 1');
        $sheet->setCellValue('H5', 350);

        $sheet->setCellValue('H6', 350);

        return self::save($spreadsheet);
    }

    /**
     * Activity rows followed by an empty-treatment subtotal line.
     */
    public static function emptyTreatmentSubtotalClosesSectionWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('10');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('G4', 'RCF x 1');
        $sheet->setCellValue('J4', 250);

        $sheet->setCellValue('J5', 250);

        return self::save($spreadsheet);
    }

    private static function writeClinicHeaderRow(Worksheet $sheet, int $row): void
    {
        $sheet->fromArray([
            ['Date', 'Name', 'MRN', 'File', 'Total Cost', 'Disc.', 'Treatment', 'Dhs', '$/Euro', 'Visa', 'crown'],
        ], null, 'A'.$row);
    }

    private static function save(Spreadsheet $spreadsheet): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'nameless-treatment-rows-').'.xlsx';
        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }
}
