<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Anonymized Clinic 111 fixtures for hybrid subtotal / doctor-marker rows.
 */
final class HybridDoctorSubtotalFixtureBuilder
{
    /**
     * Hybrid row: Riyadh subtotal (Visa 1400) and next-doctor marker (Dr Wael) on the same line.
     */
    public static function riyadhHybridSubtotalWithWaelMarkerWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('16');

        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('G2', 'Dr. Riyadh');

        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('G4', 'X-ray');
        $sheet->setCellValue('J4', 100);

        $sheet->setCellValue('B5', 'Patient B');
        $sheet->setCellValue('G5', 'ZIR x 1');
        $sheet->setCellValue('J5', 1300);

        $sheet->setCellValue('G6', 'Dr Wael');
        $sheet->setCellValue('H6', 0);
        $sheet->setCellValue('J6', 1400);

        $sheet->setCellValue('G7', 'Dr Wael');
        self::writeClinicHeaderRow($sheet, 8);

        return self::save($spreadsheet);
    }

    /**
     * Normal doctor marker row without payment values on the same line.
     */
    public static function normalDoctorMarkerSwitchWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('8');

        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('G2', 'DR Jack');

        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('H4', 100);
        $sheet->setCellValue('H5', 100);

        $sheet->setCellValue('G6', 'DR Pouria');

        self::writeClinicHeaderRow($sheet, 7);
        $sheet->setCellValue('B8', 'Patient B');
        $sheet->setCellValue('G8', 'RCT x 1');
        $sheet->setCellValue('J8', 250);
        $sheet->setCellValue('J9', 250);

        return self::save($spreadsheet);
    }

    /**
     * Normal subtotal row with empty treatment column (no doctor marker in G).
     */
    public static function normalSubtotalWithoutDoctorMarkerWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('9');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('G4', 'EXO x 1');
        $sheet->setCellValue('H4', 350);
        $sheet->setCellValue('J4', 700);

        $sheet->setCellValue('H5', 350);
        $sheet->setCellValue('J5', 700);

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
        $filePath = tempnam(sys_get_temp_dir(), 'hybrid-doctor-subtotal-').'.xlsx';
        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }
}
