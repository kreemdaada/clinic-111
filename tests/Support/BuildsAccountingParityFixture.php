<?php

namespace Tests\Support;

use App\Enums\PaymentMethod;
use App\Enums\ReportSourceType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorIncomeExportProfile;
use App\Models\Nurse;
use App\Models\NurseCommissionRate;
use App\Models\Payment;
use App\Models\Treatment;
use App\Services\Accounting\NurseCommissionCalculationService;
use App\Services\Accounting\PaymentCalculationService;
use App\Services\Import\DailyReportImportService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait BuildsAccountingParityFixture
{
    protected function buildAccountingParityFixture(): AccountingParityFixture
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $mcTreatment = Treatment::query()->where('code', 'MC')->firstOrFail();

        $opgNormal = $this->createParityOpgTreatment('OPG_NORMAL', 'OPG-Normal', '200.00');
        $opg3d = $this->createParityOpgTreatment('OPG_3D', 'OPG 3D', '360.00');

        $opgNormalNurse = $this->createParityNurseWithRate($opgNormal, '5.00');
        $opg3dNurse = $this->createParityNurseWithRate($opg3d, '5.00');

        $report = $this->createDailyReport([
            'report_date' => AccountingParityFixture::WORK_DATE,
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'JACK · 1 Apr – 30 Apr 2028',
            'status' => 'uploaded',
        ]);

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => AccountingParityFixture::WORK_DATE,
            'treatment_text' => 'OPG_NORMAL x 3, OPG_3D x 1, MC x 1',
            'dhs_amount' => AccountingParityFixture::DHS_PAYMENT,
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '0.00',
            'usd_to_aed_amount' => '0.00',
            'visa_amount' => '0.00',
            'paid_total_aed' => AccountingParityFixture::DHS_PAYMENT,
            'raw_data_json' => [
                'nurse_assignments' => [
                    'OPG_NORMAL' => $opgNormalNurse->id,
                    'OPG_3D' => $opg3dNurse->id,
                ],
            ],
        ]);

        $this->createWorkItem($workRow, [
            'treatment_id' => $opgNormal->id,
            'quantity' => 3,
            'nurse_id' => $opgNormalNurse->id,
        ]);

        $this->createWorkItem($workRow, [
            'treatment_id' => $opg3d->id,
            'quantity' => 1,
            'nurse_id' => $opg3dNurse->id,
        ]);

        $this->createWorkItem($workRow, [
            'treatment_id' => $mcTreatment->id,
            'quantity' => 1,
        ]);

        app(PaymentCalculationService::class)->createPaymentsForWorkRow($workRow->fresh());
        app(DailyReportImportService::class)->processParsedReport($report->fresh());

        $profile = DoctorIncomeExportProfile::query()
            ->where('doctor_id', $doctor->id)
            ->firstOrFail();

        $firstDayRow = (int) $profile->first_day_row;
        $daysInMonth = Carbon::createFromFormat('Y-m', AccountingParityFixture::MONTH)->daysInMonth;

        return new AccountingParityFixture(
            doctor: $doctor->fresh(),
            report: $report->fresh(),
            workRow: $workRow->fresh(),
            opgNormalTreatment: $opgNormal,
            opg3dTreatment: $opg3d,
            mcTreatment: $mcTreatment,
            opgNormalNurse: $opgNormalNurse,
            opg3dNurse: $opg3dNurse,
            exportDayRow: $firstDayRow,
            exportTotalRow: $firstDayRow + $daysInMonth,
        );
    }

    protected function buildClinic222AccountingIsolationNoise(): void
    {
        $clinic = Clinic::query()->where('code', 'CLINIC_222')->firstOrFail();
        $doctor = Doctor::query()
            ->where('clinic_id', $clinic->id)
            ->where('code', 'C222_DOC')
            ->firstOrFail();

        $opgNormal = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'OPG-NORMAL',
            'name' => 'Clinic 222 OPG',
            'treatment_price' => '200.00',
            'treatment_price_currency' => 'AED',
        ]);
        $opgNormal->requires_nurse_commission = true;
        $opgNormal->has_lab_cost = false;
        $opgNormal->is_active = true;
        $opgNormal->save();

        $nurse = Nurse::factory()->create([
            'clinic_id' => $clinic->id,
            'is_active' => true,
        ]);

        NurseCommissionRate::factory()->create([
            'clinic_id' => $clinic->id,
            'nurse_id' => $nurse->id,
            'treatment_id' => $opgNormal->id,
            'commission_percentage' => '5.00',
            'is_active' => true,
        ]);

        $report = $this->createClinic222DailyReport([
            'report_date' => AccountingParityFixture::WORK_DATE,
            'source_type' => ReportSourceType::ExcelUpload,
            'source_file_name' => 'Clinic 222 '.AccountingParityFixture::MONTH.'.xlsx',
            'status' => 'calculated',
        ]);

        $row = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => AccountingParityFixture::WORK_DATE,
            'paid_total_aed' => AccountingParityFixture::CLINIC_B_REVENUE_AED,
            'raw_data_json' => [
                'nurse_assignments' => [
                    'OPG-NORMAL' => $nurse->id,
                ],
            ],
        ]);

        Payment::query()->create([
            'clinic_id' => $clinic->id,
            'daily_work_row_id' => $row->id,
            'payment_method' => PaymentMethod::Dhs,
            'amount' => AccountingParityFixture::CLINIC_B_REVENUE_AED,
            'currency' => 'AED',
            'exchange_rate' => 1,
            'amount_aed' => AccountingParityFixture::CLINIC_B_REVENUE_AED,
            'paid_at' => AccountingParityFixture::WORK_DATE,
        ]);

        $this->createWorkItem($row, [
            'treatment_id' => $opgNormal->id,
            'quantity' => 2,
            'nurse_id' => $nurse->id,
        ]);

        app(NurseCommissionCalculationService::class)
            ->calculateForWorkRow($row->fresh(['workItems.treatment', 'workItems.nurse']));
    }

    private function createParityOpgTreatment(string $code, string $name, string $price): Treatment
    {
        $treatment = Treatment::query()->create($this->withClinicId([
            'code' => $code,
            'name' => $name,
            'treatment_price' => $price,
            'treatment_price_currency' => 'AED',
        ]));
        $treatment->requires_nurse_commission = true;
        $treatment->has_lab_cost = false;
        $treatment->is_active = true;
        $treatment->save();

        return $treatment->fresh();
    }

    private function createParityNurseWithRate(Treatment $treatment, string $percentage): Nurse
    {
        $nurse = Nurse::factory()->create([
            'clinic_id' => $treatment->clinic_id,
            'is_active' => true,
        ]);

        NurseCommissionRate::factory()->create([
            'clinic_id' => $treatment->clinic_id,
            'nurse_id' => $nurse->id,
            'treatment_id' => $treatment->id,
            'commission_percentage' => $percentage,
            'is_active' => true,
        ]);

        return $nurse;
    }
}
