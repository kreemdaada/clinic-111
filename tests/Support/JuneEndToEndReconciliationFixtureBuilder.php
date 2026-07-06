<?php

namespace Tests\Support;

use DateTime;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Anonymized June 2026 workbook combining parser-risk patterns for end-to-end reconciliation.
 *
 * Doctor A = DR Jack, Doctor B = DR Pouria, Doctor C = Dr. Riyadh, Nurse A = NurseA.
 */
final class JuneEndToEndReconciliationFixtureBuilder
{
    public static function juneEndToEndWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $definitions = [
            ['day' => '3', 'writer' => 'writeSheet3Opg'],
            ['day' => '7', 'writer' => 'writeSheet7TemplateYearJack'],
            ['day' => '16', 'writer' => 'writeSheet16HybridRiyadh'],
            ['day' => '19', 'writer' => 'writeSheet19TransfersAndOpg'],
            ['day' => '22', 'writer' => 'writeSheet22ThreeDoctors'],
            ['day' => '24', 'writer' => 'writeSheet24TransferJackToRiyadh'],
            ['day' => '26', 'writer' => 'writeSheet26NamelessAndTransfer'],
            ['day' => '30', 'writer' => 'writeSheet30TransferAndOpg'],
        ];

        foreach ($definitions as $index => $definition) {
            if ($index === 0) {
                $sheet = $spreadsheet->getActiveSheet();
            } else {
                $sheet = $spreadsheet->createSheet();
            }

            $sheet->setTitle((string) $definition['day']);
            self::{$definition['writer']}($sheet);
        }

        return self::save($spreadsheet);
    }

    private static function writeSheet3Opg(Worksheet $sheet): void
    {
        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('G8', 'OPG');
        self::writeClinicHeaderRow($sheet, 9);
        $sheet->setCellValue('B10', 'Patient 1');
        $sheet->setCellValue('G10', 'OPG trast');
        $sheet->setCellValue('H10', 200);
    }

    private static function writeSheet7TemplateYearJack(Worksheet $sheet): void
    {
        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('A4', self::excelDate('2025-06-07'));
        $sheet->setCellValue('B4', 'Patient 1');
        $sheet->setCellValue('D4', 'NF 5685');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('H4', 1850);
        $sheet->setCellValue('J4', 2100);

        $sheet->setCellValue('H5', 1850);
        $sheet->setCellValue('J5', 2100);
    }

    private static function writeSheet16HybridRiyadh(Worksheet $sheet): void
    {
        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('G2', 'Dr. Riyadh');

        $sheet->setCellValue('B4', 'Patient 1');
        $sheet->setCellValue('G4', 'X-ray');
        $sheet->setCellValue('J4', 100);

        $sheet->setCellValue('B5', 'Patient 2');
        $sheet->setCellValue('G5', 'ZIR x 1');
        $sheet->setCellValue('J5', 1300);

        $sheet->setCellValue('G6', 'Dr Wael');
        $sheet->setCellValue('H6', 0);
        $sheet->setCellValue('J6', 1400);

        $sheet->setCellValue('G7', 'Dr Wael');
        self::writeClinicHeaderRow($sheet, 8);
    }

    private static function writeSheet19TransfersAndOpg(Worksheet $sheet): void
    {
        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('B4', 'Patient 1');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('H4', 200);
        $sheet->setCellValue('J4', 500);
        $sheet->setCellValue('H5', 200);
        $sheet->setCellValue('J5', 500);

        $sheet->setCellValue('G7', 'DR Pouria');
        self::writeClinicHeaderRow($sheet, 8);
        $sheet->setCellValue('G9', 'Transfer to dr Riyadh');
        $sheet->setCellValue('H9', -450);
        $sheet->setCellValue('H10', -450);

        $sheet->setCellValue('G12', 'Dr. Riyadh');
        self::writeClinicHeaderRow($sheet, 13);
        $sheet->setCellValue('B14', 'Patient 2');
        $sheet->setCellValue('G14', 'RCF x 1');
        $sheet->setCellValue('H14', 450);
        $sheet->setCellValue('H15', 450);

        $sheet->setCellValue('G17', 'OPG');
        self::writeOpgHeaderRowWithVisaInK($sheet, 18);
        $sheet->setCellValue('B19', 'Patient 3');
        $sheet->setCellValue('G19', 'OPG');
        $sheet->setCellValue('K19', 200);
        $sheet->setCellValue('L19', 'NurseA');
    }

    private static function writeSheet22ThreeDoctors(Worksheet $sheet): void
    {
        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('A4', self::excelDate('2025-06-22'));
        $sheet->setCellValue('B4', 'Patient 1');
        $sheet->setCellValue('D4', 'NF 5685');
        $sheet->setCellValue('G4', 'REMOV x 6');
        $sheet->setCellValue('H4', 1000);

        $sheet->setCellValue('B5', 'Patient 2');
        $sheet->setCellValue('D5', 'NF 6089');
        $sheet->setCellValue('G5', 'MC x 4');

        $sheet->setCellValue('B6', 'Patient 3');
        $sheet->setCellValue('D6', 'NF 5986');
        $sheet->setCellValue('G6', 'IMPLZIR x 4 + ABB x 4');
        $sheet->setCellValue('J6', 8400);

        $sheet->setCellValue('B7', 'Patient 4');
        $sheet->setCellValue('D7', 'NF 5980');
        $sheet->setCellValue('G7', 'CF x 1');
        $sheet->setCellValue('J7', 350);

        $sheet->setCellValue('G8', 'PAID BALANCE');
        $sheet->setCellValue('J8', 700);

        $sheet->setCellValue('B9', 'Patient 5');
        $sheet->setCellValue('D9', 'NF 6092');
        $sheet->setCellValue('G9', 'POST X 2');
        $sheet->setCellValue('H9', 500);

        $sheet->setCellValue('H10', 1500);
        $sheet->setCellValue('J10', 9450);

        $sheet->setCellValue('G12', 'DR Pouria');
        self::writeClinicHeaderRow($sheet, 13);
        $sheet->setCellValue('A14', self::excelDate('2025-06-22'));
        $sheet->setCellValue('B14', 'Patient 6');
        $sheet->setCellValue('D14', 'NF 6089');
        $sheet->setCellValue('G14', 'CF x 1');
        $sheet->setCellValue('H14', 350);
        $sheet->setCellValue('H15', 350);

        $sheet->setCellValue('G17', 'Dr. Riyadh');
        self::writeClinicHeaderRow($sheet, 18);
        $sheet->setCellValue('A19', self::excelDate('2025-06-22'));
        $sheet->setCellValue('B19', 'Patient 7');
        $sheet->setCellValue('G19', 'CF x 1');
        $sheet->setCellValue('J19', 350);

        $sheet->setCellValue('B20', 'Patient 8');
        $sheet->setCellValue('G20', 'PAID BALANCE');
        $sheet->setCellValue('H20', 500);

        $sheet->setCellValue('B21', 'Patient 9');
        $sheet->setCellValue('G21', 'RCF x 1 + POST x 1');
        $sheet->setCellValue('H21', 500);

        $sheet->setCellValue('B22', 'Patient 10');
        $sheet->setCellValue('G22', 'ZIR x 1');
        $sheet->setCellValue('H22', 10000);

        $sheet->setCellValue('B23', 'Patient 11');
        $sheet->setCellValue('G23', 'CF x 1');
        $sheet->setCellValue('H23', 250);

        $sheet->setCellValue('H24', 11250);
        $sheet->setCellValue('J24', 350);
    }

    private static function writeSheet24TransferJackToRiyadh(Worksheet $sheet): void
    {
        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('G4', 'paid balance');
        $sheet->setCellValue('J4', 200);
        $sheet->setCellValue('G5', 'Transfer to dr Riyadh');
        $sheet->setCellValue('H5', -700);
        $sheet->setCellValue('H6', -500);
        $sheet->setCellValue('J6', 200);

        $sheet->setCellValue('G8', 'Dr. Riyadh');
        self::writeClinicHeaderRow($sheet, 9);
        $sheet->setCellValue('G10', 'TRANSFER FROM DR JACK');
        $sheet->setCellValue('H10', 700);
        $sheet->setCellValue('H11', 700);
    }

    private static function writeSheet26NamelessAndTransfer(Worksheet $sheet): void
    {
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

        $sheet->setCellValue('G13', 'DR Pouria');
        self::writeClinicHeaderRow($sheet, 14);
        $sheet->setCellValue('G15', 'TRANSFER TO DR JACK');
        $sheet->setCellValue('H15', -350);
        $sheet->setCellValue('H16', -350);
    }

    private static function writeSheet30TransferAndOpg(Worksheet $sheet): void
    {
        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('G4', 'transfer to dr riyadh');
        $sheet->setCellValue('H4', -7200);
        $sheet->setCellValue('H5', -7200);

        $sheet->setCellValue('G7', 'DR Pouria');
        self::writeClinicHeaderRow($sheet, 8);
        $sheet->setCellValue('B9', 'Patient 1');
        $sheet->setCellValue('G9', 'RCF x 1 + CF x 1');
        $sheet->setCellValue('H9', 550);
        $sheet->setCellValue('H10', 550);

        $sheet->setCellValue('G12', 'Dr. Riyadh');
        self::writeClinicHeaderRow($sheet, 13);
        $sheet->setCellValue('G14', 'transfer from dr jack');
        $sheet->setCellValue('H14', 7200);
        $sheet->setCellValue('H15', 7200);

        $sheet->setCellValue('G17', 'OPG');
        self::writeClinicHeaderRow($sheet, 18);
        $sheet->setCellValue('B19', 'Patient 2');
        $sheet->setCellValue('G19', 'OPG - NurseA');
        $sheet->setCellValue('J19', 200);
    }

    /**
     * Isolated transfer only: Doctor A −450, Doctor B +450, no other payments (sheet day 11).
     */
    public static function isolatedTransferOnlyWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('11');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('G4', 'Transfer to dr Pouria');
        $sheet->setCellValue('H4', -450);
        $sheet->setCellValue('H5', -450);

        $sheet->setCellValue('G7', 'DR Pouria');
        self::writeClinicHeaderRow($sheet, 8);
        $sheet->setCellValue('G9', 'TRANSFER FROM DR JACK');
        $sheet->setCellValue('H9', 450);
        $sheet->setCellValue('H10', 450);

        return self::save($spreadsheet);
    }

    /**
     * Patient payment 1000 plus transfer 450 Doctor A → Doctor B (sheet day 12).
     */
    public static function transferWithPatientPaymentWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('12');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('B4', 'Patient 1');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('H4', 1000);
        $sheet->setCellValue('G5', 'Transfer to dr Pouria');
        $sheet->setCellValue('H5', -450);
        $sheet->setCellValue('H6', 550);

        $sheet->setCellValue('G8', 'DR Pouria');
        self::writeClinicHeaderRow($sheet, 9);
        $sheet->setCellValue('G10', 'TRANSFER FROM DR JACK');
        $sheet->setCellValue('H10', 450);
        $sheet->setCellValue('H11', 450);

        return self::save($spreadsheet);
    }

    private static function excelDate(string $isoDate): float
    {
        return Date::PHPToExcel(new DateTime($isoDate));
    }

    private static function writeClinicHeaderRow(Worksheet $sheet, int $row): void
    {
        $sheet->fromArray([
            ['Date', 'Name', 'MRN', 'File', 'Total Cost', 'Disc.', 'Treatment', 'Dhs', '$/Euro', 'Visa', 'Nurse', 'crown'],
        ], null, 'A'.$row);
    }

    private static function writeOpgHeaderRowWithVisaInK(Worksheet $sheet, int $row): void
    {
        $sheet->fromArray([
            ['Date', 'Name', 'MRN', 'File', 'Total Cost', 'Disc.', 'Treatment', 'Dhs', '$', '$/Euro', 'Visa', 'Nurse'],
        ], null, 'A'.$row);
    }

    private static function save(Spreadsheet $spreadsheet): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'june-e2e-reconciliation-').'.xlsx';
        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }
}
