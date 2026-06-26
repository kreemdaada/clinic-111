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
}
