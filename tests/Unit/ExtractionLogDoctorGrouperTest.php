<?php

namespace Tests\Unit;

use App\Support\DoctorCodeResolver;
use App\Support\ExtractionLogDoctorGrouper;
use App\Support\OpgClinicDoctor;
use PHPUnit\Framework\TestCase;

class ExtractionLogDoctorGrouperTest extends TestCase
{
    public function test_skipped_dr_jack_rows_merge_into_jack_totals(): void
    {
        $log = [
            'imported_rows' => [
                [
                    'doctor_code' => 'JACK',
                    'doctor_label' => 'DR Jack',
                    'paid_total_aed' => '1000.00',
                    'lab_total_aed' => '200.00',
                    'issues' => [],
                ],
            ],
            'skipped_rows' => [
                [
                    'doctor_label' => 'DR Jack',
                    'reason' => 'cash_row',
                ],
                [
                    'doctor_label' => 'DR Jack',
                    'reason' => 'grand_total_row',
                ],
            ],
            'unresolved_rows' => [],
        ];

        $totals = ExtractionLogDoctorGrouper::knownDoctorTotals($log);

        $this->assertArrayHasKey('JACK', $totals);
        $this->assertArrayNotHasKey('DR Jack', $totals);
        $this->assertSame(1, $totals['JACK']['day_count']);
        $this->assertSame(2, $totals['JACK']['skipped_rows_on_sheet']);
    }

    public function test_unknown_doctor_is_grouped_as_error_not_summary_duplicate(): void
    {
        $log = [
            'imported_rows' => [],
            'skipped_rows' => [],
            'unresolved_rows' => [
                [
                    'doctor_label' => 'Dr. Anas',
                    'sheet_day' => 3,
                    'excel_row' => 12,
                    'treatment_text' => 'ZIR x 2',
                    'dhs_aed' => '500.00',
                ],
            ],
        ];

        $totals = ExtractionLogDoctorGrouper::knownDoctorTotals($log);
        $errors = ExtractionLogDoctorGrouper::unknownDoctorErrors($log);

        $this->assertSame([], $totals);
        $this->assertCount(1, $errors);
        $this->assertSame('Dr. Anas', $errors[0]['label']);
        $this->assertSame('ZIR x 2', $errors[0]['rows'][0]['treatment_text']);
    }

    public function test_dr_riyadh_skipped_rows_merge_into_riyad(): void
    {
        $resolved = DoctorCodeResolver::resolve(null, 'Dr. Riyadh');

        $this->assertTrue($resolved['is_known']);
        $this->assertSame('RIYAD', $resolved['code']);
    }

    public function test_grand_total_skipped_rows_are_not_unknown_doctor_errors(): void
    {
        $log = [
            'imported_rows' => [],
            'skipped_rows' => [
                [
                    'doctor_label' => null,
                    'reason' => 'grand_total_row',
                    'treatment_text' => 'TOTAL',
                    'dhs_aed' => '2000.00',
                    'visa_aed' => '1200.00',
                ],
            ],
            'unresolved_rows' => [],
        ];

        $errors = ExtractionLogDoctorGrouper::unknownDoctorErrors($log);

        $this->assertSame([], $errors);
    }

    public function test_clinic_opg_imported_rows_are_not_unknown_doctor_errors(): void
    {
        $log = [
            'imported_rows' => [
                [
                    'doctor_code' => OpgClinicDoctor::CODE,
                    'doctor_label' => OpgClinicDoctor::IMPORT_LABEL,
                    'sheet_day' => 3,
                    'excel_row' => 10,
                    'treatment_text' => 'OPG_NORMAL x 1',
                    'paid_total_aed' => '200.00',
                    'lab_total_aed' => '0.00',
                    'issues' => [],
                ],
                [
                    'doctor_code' => OpgClinicDoctor::CODE,
                    'doctor_label' => OpgClinicDoctor::IMPORT_LABEL,
                    'sheet_day' => 19,
                    'excel_row' => 10,
                    'treatment_text' => 'OPG_NORMAL x 1',
                    'paid_total_aed' => '200.00',
                    'lab_total_aed' => '0.00',
                    'issues' => [],
                ],
                [
                    'doctor_code' => OpgClinicDoctor::CODE,
                    'doctor_label' => OpgClinicDoctor::IMPORT_LABEL,
                    'sheet_day' => 30,
                    'excel_row' => 10,
                    'treatment_text' => 'OPG_NORMAL x 1',
                    'paid_total_aed' => '200.00',
                    'lab_total_aed' => '0.00',
                    'issues' => [],
                ],
            ],
            'skipped_rows' => [],
            'unresolved_rows' => [],
        ];

        $totals = ExtractionLogDoctorGrouper::knownDoctorTotals($log);
        $errors = ExtractionLogDoctorGrouper::unknownDoctorErrors($log);

        $this->assertSame([], $totals);
        $this->assertSame([], $errors);
    }

    public function test_mixed_opg_and_unknown_doctor_reports_only_unknown_doctor(): void
    {
        $log = [
            'imported_rows' => [
                [
                    'doctor_code' => OpgClinicDoctor::CODE,
                    'doctor_label' => OpgClinicDoctor::IMPORT_LABEL,
                    'sheet_day' => 3,
                    'excel_row' => 10,
                    'treatment_text' => 'OPG_NORMAL x 1',
                ],
            ],
            'skipped_rows' => [],
            'unresolved_rows' => [
                [
                    'doctor_label' => 'Dr. Anas',
                    'sheet_day' => 5,
                    'excel_row' => 12,
                    'treatment_text' => 'ZIR x 2',
                ],
            ],
        ];

        $errors = ExtractionLogDoctorGrouper::unknownDoctorErrors($log);

        $this->assertCount(1, $errors);
        $this->assertSame('Dr. Anas', $errors[0]['label']);
        $this->assertCount(1, $errors[0]['rows']);
    }
}
