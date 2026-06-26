<?php

namespace Tests\Unit;

use App\Enums\CommissionType;
use App\Models\Clinic;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Services\Accounting\IncomeReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_usd_clinic_primary_cash_passes_reconciliation(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Syria Clinic',
            'code' => 'SYRIA_RECON',
            'currency' => 'USD',
            'timezone' => 'Asia/Damascus',
            'country' => 'Syria',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $doctor = Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'JENNIFER',
            'name' => 'Jennifer',
            'commission_type' => CommissionType::Percentage,
            'commission_percentage' => 35,
        ]);
        $doctor->is_active = true;
        $doctor->save();

        $report = DailyReport::query()->create([
            'clinic_id' => $clinic->id,
            'report_date' => '2026-06-01',
            'source_type' => 'manual_entry',
            'source_file_name' => 'JENNIFER · 1 Jun – 29 Jun 2026',
            'status' => 'needs_review',
        ]);

        DailyWorkRow::query()->create([
            'clinic_id' => $clinic->id,
            'daily_report_id' => $report->id,
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
            'treatment_text' => 'ZIR x 2',
            'dhs_amount' => '105.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '0.00',
            'usd_to_aed_amount' => '0.00',
            'visa_amount' => '0.00',
            'paid_total_aed' => '383.25',
        ]);

        $issues = app(IncomeReconciliationService::class)->validateReport($report->fresh());

        $this->assertSame([], $issues);
    }
}
