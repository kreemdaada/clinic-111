<?php

namespace Tests\Feature;

use App\Enums\CommissionType;
use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\Clinic;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\User;
use App\Services\Export\DoctorsIncomeExcelExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Tests\TestCase;

class DoctorsIncomeExcelExportClinicTest extends TestCase
{
    use RefreshDatabase;

    public function test_syria_income_export_contains_only_current_clinic_doctor_sheet(): void
    {
        $this->seedAccountingData();

        $clinic = Clinic::query()->create([
            'name' => 'Syria Clinic',
            'code' => 'SYRIA_EXPORT',
            'currency' => 'USD',
            'timezone' => 'Asia/Damascus',
            'country' => 'Syria',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $user = new User;
        $user->fill([
            'name' => 'Syria Admin',
            'email' => 'syria-export@test.local',
            'role' => 'admin',
            'clinic_id' => $clinic->id,
        ]);
        $user->password = 'password';
        $user->is_active = true;
        $user->save();

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
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'JENNIFER · 1 Jun – 29 Jun 2026',
            'status' => ReportStatus::NeedsReview,
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

        $this->actingAs($user);

        $path = app(DoctorsIncomeExcelExportService::class)->exportForReport($report);
        $spreadsheet = IOFactory::load($path);
        $sheetNames = $spreadsheet->getSheetNames();

        $this->assertSame(['Dr. Jennifer'], $sheetNames);

        $sheet = $spreadsheet->getSheetByName('Dr. Jennifer');
        $this->assertNotNull($sheet);
        $this->assertSame(105.0, (float) $sheet->getCell('B3')->getCalculatedValue());
        $this->assertSame(105.0, (float) $sheet->getCell('F3')->getCalculatedValue());
        $this->assertSame(
            NumberFormat::FORMAT_DATE_DDMMYYYY,
            $sheet->getStyle('A3')->getNumberFormat()->getFormatCode(),
        );
        $this->assertGreaterThan(40000, (float) $sheet->getCell('A3')->getCalculatedValue());
    }

    public function test_usd_clinic_new_doctor_export_uses_standard_layout_and_clinic_currency(): void
    {
        config(['accounting.usd_exchange_rate' => '3.65']);

        $clinic = Clinic::query()->create([
            'name' => 'Clinic Test 2',
            'code' => 'CLINIC-TEST-EXPORT',
            'currency' => 'USD',
            'timezone' => 'Europe/Berlin',
            'country' => 'Germany',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $user = new User;
        $user->fill([
            'name' => 'Test Admin',
            'email' => 'test-export@test.local',
            'role' => 'admin',
            'clinic_id' => $clinic->id,
        ]);
        $user->password = 'password';
        $user->is_active = true;
        $user->save();

        $this->actingAs($user);

        $doctor = Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'TESTDR',
            'name' => 'TestDr',
            'commission_type' => CommissionType::Percentage,
            'commission_percentage' => 35,
        ]);
        $doctor->is_active = true;
        $doctor->save();

        $treatment = \App\Models\Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'IMPL-ZIR',
            'name' => 'Implant Zircon',
        ]);
        $treatment->has_lab_cost = true;
        $treatment->is_active = true;
        $treatment->save();

        \App\Models\DoctorLabBilling::query()->create([
            'doctor_id' => $doctor->id,
            'treatment_id' => $treatment->id,
            'bill_lab_job' => true,
        ]);

        $lab = \App\Models\Lab::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Main Lab',
            'code' => 'MAIN',
        ]);
        $lab->is_active = true;
        $lab->save();

        $price = \App\Models\LabPrice::query()->create([
            'clinic_id' => $clinic->id,
            'lab_id' => $lab->id,
            'treatment_id' => $treatment->id,
            'unit_cost' => '12.00',
            'currency' => 'USD',
        ]);
        $price->is_active = true;
        $price->save();

        $report = DailyReport::query()->create([
            'clinic_id' => $clinic->id,
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'TESTDR · 1 Jun – 1 Jun 2026',
            'status' => ReportStatus::Calculated,
        ]);

        $workRow = DailyWorkRow::query()->create([
            'clinic_id' => $clinic->id,
            'daily_report_id' => $report->id,
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
            'treatment_text' => 'IMPL-ZIR x 1',
            'dhs_amount' => '105.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '0.00',
            'usd_to_aed_amount' => '0.00',
            'visa_amount' => '0.00',
            'paid_total_aed' => '383.25',
        ]);

        \App\Models\WorkItem::query()->create([
            'clinic_id' => $clinic->id,
            'daily_work_row_id' => $workRow->id,
            'treatment_id' => $treatment->id,
            'quantity' => 1,
            'confidence' => 100,
        ]);

        app(\App\Services\Import\DailyReportImportService::class)->processParsedReport($report->fresh());

        $path = app(DoctorsIncomeExcelExportService::class)->exportForReport($report->fresh());
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Dr. TestDr');
        $this->assertNotNull($sheet);

        $this->assertSame('USD', $sheet->getCell('B1')->getCalculatedValue());
        $this->assertSame('AED', $sheet->getCell('C1')->getCalculatedValue());
        $this->assertSame('IMPL-ZIR', $sheet->getCell('K1')->getCalculatedValue());
        $this->assertSame(105.0, (float) $sheet->getCell('B3')->getCalculatedValue());
        $this->assertSame(105.0, (float) $sheet->getCell('F3')->getCalculatedValue());
        $this->assertSame(12.0, (float) $sheet->getCell('G3')->getCalculatedValue());
        $this->assertSame(1.0, (float) $sheet->getCell('K3')->getCalculatedValue());

        $this->assertDatabaseHas('doctor_income_export_profiles', [
            'doctor_id' => $doctor->id,
            'sheet_name' => 'Dr. TestDr',
        ]);
    }
}
