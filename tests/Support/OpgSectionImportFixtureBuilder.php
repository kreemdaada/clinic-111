<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Anonymized Clinic 111 fixtures for OPG section import.
 */
final class OpgSectionImportFixtureBuilder
{
    public static function tag3OpgDhsWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('3');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('H4', 100);

        $sheet->setCellValue('G8', 'OPG');
        self::writeClinicHeaderRow($sheet, 9);
        $sheet->setCellValue('B10', 'Patient B');
        $sheet->setCellValue('G10', 'OPG trast');
        $sheet->setCellValue('H10', 200);

        return self::save($spreadsheet);
    }

    public static function opgWithUnmappedCommentWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('6');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('G8', 'OPG');
        self::writeClinicHeaderRow($sheet, 9);
        $sheet->setCellValue('G10', 'OPG');
        $sheet->setCellValue('J10', 200);
        $sheet->setCellValue('L10', 'paid balance');

        return self::save($spreadsheet);
    }

    public static function tag19OpgVisaInColumnKWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('19');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('H4', 200);

        $sheet->setCellValue('G8', 'OPG');
        self::writeOpgHeaderRowWithVisaInK($sheet, 9);
        $sheet->setCellValue('B10', 'Patient B');
        $sheet->setCellValue('G10', 'OPG');
        $sheet->setCellValue('K10', 200);
        $sheet->setCellValue('L10', 'Jiji');

        return self::save($spreadsheet);
    }

    public static function tag30OpgNurseInTreatmentTextWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('30');

        $sheet->setCellValue('G2', 'DR Pouria');
        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('G4', 'RCF x 1');
        $sheet->setCellValue('H4', 550);

        $sheet->setCellValue('G8', 'OPG');
        self::writeClinicHeaderRow($sheet, 9);
        $sheet->setCellValue('B10', 'Patient B');
        $sheet->setCellValue('G10', 'OPG - Jiji');
        $sheet->setCellValue('J10', 200);

        return self::save($spreadsheet);
    }

    public static function opg3dBeforeNormalWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('8');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);

        $sheet->setCellValue('G8', 'OPG');
        self::writeClinicHeaderRow($sheet, 9);
        $sheet->setCellValue('G10', 'OPG 3D');
        $sheet->setCellValue('H10', 360);

        return self::save($spreadsheet);
    }

    public static function doctorThenOpgIsolationWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('12');

        $sheet->setCellValue('G2', 'DR Jack');
        self::writeClinicHeaderRow($sheet, 3);
        $sheet->setCellValue('B4', 'Patient A');
        $sheet->setCellValue('G4', 'CF x 1');
        $sheet->setCellValue('H4', 100);
        $sheet->setCellValue('H5', 100);

        $sheet->setCellValue('G7', 'OPG');
        self::writeClinicHeaderRow($sheet, 8);
        $sheet->setCellValue('G9', 'OPG');
        $sheet->setCellValue('J9', 200);

        $sheet->setCellValue('G11', 'DR Pouria');
        self::writeClinicHeaderRow($sheet, 12);
        $sheet->setCellValue('B13', 'Patient B');
        $sheet->setCellValue('G13', 'SxP');
        $sheet->setCellValue('H13', 350);
        $sheet->setCellValue('H14', 350);

        return self::save($spreadsheet);
    }

    public static function juneOpgMonthWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $definitions = [
            ['day' => '3', 'paymentColumn' => 'H', 'payment' => 200, 'treatment' => 'OPG trast', 'visaHeader' => false],
            ['day' => '19', 'paymentColumn' => 'K', 'payment' => 200, 'treatment' => 'OPG', 'nurseColumn' => 'L', 'nurse' => 'Jiji', 'visaHeader' => true],
            ['day' => '30', 'paymentColumn' => 'J', 'payment' => 200, 'treatment' => 'OPG - Jiji', 'visaHeader' => false],
        ];

        foreach ($definitions as $index => $definition) {
            if ($index === 0) {
                $sheet = $spreadsheet->getActiveSheet();
            } else {
                $sheet = $spreadsheet->createSheet();
            }

            $sheet->setTitle((string) $definition['day']);
            $sheet->setCellValue('G2', 'DR Jack');

            if ($definition['visaHeader']) {
                self::writeOpgHeaderRowWithVisaInK($sheet, 3);
            } else {
                self::writeClinicHeaderRow($sheet, 3);
            }

            $sheet->setCellValue('G8', 'OPG');

            if ($definition['visaHeader']) {
                self::writeOpgHeaderRowWithVisaInK($sheet, 9);
            } else {
                self::writeClinicHeaderRow($sheet, 9);
            }

            $sheet->setCellValue('G10', $definition['treatment']);
            $sheet->setCellValue($definition['paymentColumn'].'10', $definition['payment']);

            if (isset($definition['nurseColumn'], $definition['nurse'])) {
                $sheet->setCellValue($definition['nurseColumn'].'10', $definition['nurse']);
            }
        }

        return self::save($spreadsheet);
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
        $filePath = tempnam(sys_get_temp_dir(), 'opg-section-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }
}
