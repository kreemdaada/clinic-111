<?php

namespace Tests\Unit;

use App\Support\DoctorCodeResolver;
use App\Support\ExtractionLogDoctorGrouper;
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
}
