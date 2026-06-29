<?php

namespace App\Services\DailyReport;

use App\Enums\CommissionType;
use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\Treatment;
use App\Services\Accounting\Concerns\ScopesAccountingQueries;
use App\Services\Accounting\LabBillingResolver;
use App\Services\Accounting\LabPriceResolver;
use App\Services\Accounting\PaymentCalculationService;
use App\Services\Accounting\TreatmentParserService;
use App\Services\Accounting\WaelFixedFeeCalculator;
use App\Services\Audit\AuditLogService;
use App\Services\Configuration\CurrentClinicResolver;
use App\Services\Import\DailyReportImportService;
use App\Support\AccountingScopedQuery;
use App\Support\ClinicCurrencySupport;
use App\Support\MoneyCalculator;
use App\Support\TreatmentTextBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * V2 manual daily report editor — create reports and work rows without Excel.
 */
class DailyReportEditorService
{
    use ScopesAccountingQueries;

    public function __construct(
        private readonly PaymentCalculationService $paymentCalculationService,
        private readonly TreatmentParserService $treatmentParserService,
        private readonly LabBillingResolver $labBillingResolver,
        private readonly LabPriceResolver $labPriceResolver,
        private readonly DailyReportImportService $dailyReportImportService,
        private readonly AuditLogService $auditLogService,
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    public function createManualReport(Carbon $monthStart, string $label): DailyReport
    {
        $reportDate = $monthStart->copy()->startOfMonth()->toDateString();

        if ($this->forCurrentClinic(DailyReport::class)
            ->where('report_date', $reportDate)
            ->whereIn('status', [ReportStatus::Approved, ReportStatus::Locked])
            ->exists()
        ) {
            throw new RuntimeException('An approved or locked report already exists for this month.');
        }

        return DailyReport::query()->create([
            'clinic_id' => $this->currentClinicId(),
            'report_date' => $reportDate,
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => $label,
            'status' => ReportStatus::Uploaded,
        ]);
    }

    /**
     * @param  array{
     *     doctor_id: int,
     *     day: int,
     *     treatment_lines: array<int, array{code: string, quantity: int}>,
     *     dhs_amount?: string|float,
     *     usd_amount?: string|float,
     *     visa_amount?: string|float,
     *     work_row_id?: int|null,
     * }  $payload
     */
    public function saveWorkRow(DailyReport $dailyReport, array $payload): DailyWorkRow
    {
        $this->assertSameClinic($dailyReport);

        if ($dailyReport->isLocked()) {
            throw new RuntimeException('Approved or locked reports are read-only.');
        }

        $doctor = $this->forCurrentClinic(Doctor::class)->where('is_active', true)->findOrFail($payload['doctor_id']);
        $day = (int) $payload['day'];
        $monthStart = Carbon::parse($dailyReport->report_date)->startOfMonth();

        if ($day < 1 || $day > $monthStart->daysInMonth) {
            throw new RuntimeException('Invalid calendar day for this month.');
        }

        $workDate = $monthStart->copy()->day($day);
        $treatmentText = TreatmentTextBuilder::fromLines($payload['treatment_lines'] ?? []);

        if ($treatmentText === '') {
            throw new RuntimeException('Select at least one treatment with quantity.');
        }

        $dhs = $this->decimal($payload['dhs_amount'] ?? '0');
        $cheque = $this->decimal($payload['cheque_amount'] ?? '0');
        $tabby = $this->decimal($payload['tabby_amount'] ?? '0');
        $usd = $this->decimal($payload['usd_amount'] ?? '0');
        $visa = $this->decimal($payload['visa_amount'] ?? '0');

        $clinic = $this->currentClinicResolver->resolve();
        $clinicCurrency = ClinicCurrencySupport::baseCurrency($clinic);
        $paymentTotals = $this->paymentCalculationService->calculateTotalCollected(
            $clinic,
            $dhs,
            $usd,
            $visa,
            chequeAmount: $cheque,
            tabbyAmount: $tabby,
        );

        return DB::transaction(function () use (
            $dailyReport,
            $doctor,
            $day,
            $workDate,
            $treatmentText,
            $dhs,
            $cheque,
            $tabby,
            $usd,
            $visa,
            $paymentTotals,
            $payload,
        ) {
            $isCorrection = isset($payload['work_row_id']);
            $oldCorrectionValues = null;

            /** @var DailyWorkRow $workRow */
            $workRow = $isCorrection
                ? AccountingScopedQuery::workRows((int) $dailyReport->clinic_id, $dailyReport->id)
                ->where('id', $payload['work_row_id'])
                ->firstOrFail()
                : new DailyWorkRow([
                    'clinic_id' => $dailyReport->clinic_id,
                    'daily_report_id' => $dailyReport->id,
                ]);

            if ($isCorrection) {
                $oldCorrectionValues = [
                    'work_row_id' => $workRow->id,
                    'doctor_id' => $workRow->doctor_id,
                    'paid_total_aed' => (string) $workRow->paid_total_aed,
                    'dhs_amount' => (string) $workRow->dhs_amount,
                    'cheque_amount' => (string) $workRow->cheque_amount,
                    'tabby_amount' => (string) $workRow->tabby_amount,
                    'usd_amount' => (string) $workRow->usd_amount,
                    'visa_amount' => (string) $workRow->visa_amount,
                    'treatment_text' => $workRow->treatment_text,
                ];
            }

            $rawData = is_array($workRow->raw_data_json) ? $workRow->raw_data_json : [];
            $rawData['sheet_day'] = $day;
            $rawData['doctor'] = $doctor->code;
            $rawData['source'] = 'manual_v2';
            $rawData['corrected_in_editor'] = true;

            $workRow->fill([
                'doctor_id' => $doctor->id,
                'work_date' => $workDate->toDateString(),
                'treatment_text' => $treatmentText,
                'dhs_amount' => $dhs,
                'cheque_amount' => $cheque,
                'tabby_amount' => $tabby,
                'usd_amount' => $usd,
                'usd_to_aed_amount' => $paymentTotals['usd_to_aed_amount'],
                'visa_amount' => $visa,
                'paid_total_aed' => $paymentTotals['paid_total_aed'],
                'raw_data_json' => $rawData,
            ]);

            if ($workRow->excel_row_number === null) {
                $workRow->excel_row_number = $this->nextManualRowNumber($dailyReport, $doctor->id, $day);
            }

            $workRow->save();
            AccountingScopedQuery::payments((int) $workRow->clinic_id, $workRow->id)->delete();
            $this->paymentCalculationService->createPaymentsForWorkRow($workRow);

            $this->dailyReportImportService->processParsedReport($dailyReport->fresh());

            if ($isCorrection && $oldCorrectionValues !== null) {
                $this->auditLogService->logManualPaymentCorrection(
                    $dailyReport,
                    $oldCorrectionValues,
                    [
                        'work_row_id' => $workRow->id,
                        'doctor_id' => $workRow->doctor_id,
                        'paid_total_aed' => (string) $workRow->paid_total_aed,
                        'dhs_amount' => (string) $workRow->dhs_amount,
                        'cheque_amount' => (string) $workRow->cheque_amount,
                        'tabby_amount' => (string) $workRow->tabby_amount,
                        'usd_amount' => (string) $workRow->usd_amount,
                        'visa_amount' => (string) $workRow->visa_amount,
                        'treatment_text' => $workRow->treatment_text,
                    ],
                );
            }

            return $workRow->fresh(['doctor', 'workItems.treatment', 'workItems.labJob', 'payments']);
        });
    }

    public function deleteWorkRow(DailyReport $dailyReport, DailyWorkRow $dailyWorkRow): void
    {
        $this->assertSameClinic($dailyReport);
        $this->assertSameClinic($dailyWorkRow);

        if ($dailyReport->isLocked()) {
            throw new RuntimeException('Approved or locked reports are read-only.');
        }

        if ($dailyWorkRow->daily_report_id !== $dailyReport->id) {
            abort(404);
        }

        DB::transaction(function () use ($dailyReport, $dailyWorkRow) {
            $dailyWorkRow->delete();
            $this->dailyReportImportService->processParsedReport($dailyReport->fresh());
        });
    }

    /**
     * Preview paid, lab, net, and doctor income without persisting.
     *
     * @param  array<int, array{code: string, quantity: int}>  $treatmentLines
     * @return array<string, string>
     */
    public function previewRow(
        Doctor $doctor,
        array $treatmentLines,
        string $dhs,
        string $usd,
        string $visa,
        Carbon $workDate,
        string $cheque = '0.00',
        string $tabby = '0.00',
    ): array {
        $this->assertSameClinic($doctor);

        $dhs = $this->decimal($dhs);
        $cheque = $this->decimal($cheque);
        $tabby = $this->decimal($tabby);
        $usd = $this->decimal($usd);
        $visa = $this->decimal($visa);

        $clinic = $this->currentClinicResolver->resolve();
        $clinicCurrency = ClinicCurrencySupport::baseCurrency($clinic);
        $paymentTotals = $this->paymentCalculationService->calculateTotalCollected(
            $clinic,
            $dhs,
            $usd,
            $visa,
            chequeAmount: $cheque,
            tabbyAmount: $tabby,
        );
        $paidTotal = $paymentTotals['paid_total'];
        $treatmentText = TreatmentTextBuilder::fromLines($treatmentLines);
        $labTotal = $this->estimateLabTotal($doctor, $treatmentText, $workDate, $clinicCurrency);
        $netTotal = MoneyCalculator::subtract($paidTotal, $labTotal);
        $doctorIncome = $this->estimateDoctorIncome(
            $doctor,
            $treatmentLines,
            $paidTotal,
            $labTotal,
            $usd,
            $clinicCurrency,
        );

        return [
            'treatment_text' => $treatmentText,
            'currency' => $clinicCurrency,
            'paid_total_aed' => $paidTotal,
            'lab_total_aed' => $labTotal,
            'net_total_aed' => $netTotal,
            'doctor_income_aed' => $doctorIncome,
            'commission_percentage' => $doctor->commission_type === CommissionType::Percentage
                ? (string) ($doctor->commission_percentage ?? '0')
                : null,
        ];
    }

    /**
     * @return array<int, int> day number => row count
     */
    public function dayCountsForDoctor(DailyReport $dailyReport, int $doctorId): array
    {
        $this->assertSameClinic($dailyReport);

        $monthStart = Carbon::parse($dailyReport->report_date)->startOfMonth();
        $counts = [];

        $rows = AccountingScopedQuery::workRows((int) $dailyReport->clinic_id, $dailyReport->id)
            ->where('doctor_id', $doctorId)
            ->get(['work_date']);

        foreach ($rows as $row) {
            if ($row->work_date === null) {
                continue;
            }

            $day = Carbon::parse($row->work_date)->day;

            if ($day >= 1 && $day <= $monthStart->daysInMonth) {
                $counts[$day] = ($counts[$day] ?? 0) + 1;
            }
        }

        ksort($counts);

        return $counts;
    }

    private function estimateLabTotal(
        Doctor $doctor,
        string $treatmentText,
        Carbon $workDate,
        string $clinicCurrency,
    ): string {
        $parsed = $this->treatmentParserService->parse($treatmentText);
        $activeLabs = $this->forCurrentClinic(Lab::class)->where('is_active', true)->get();
        $total = '0.00';

        foreach ($parsed as $item) {
            $treatment = $this->forCurrentClinic(Treatment::class)->where('code', $item->treatmentCode)->first();

            if ($treatment === null || ! $this->labBillingResolver->shouldBillLabJob($doctor, $treatment)) {
                continue;
            }

            $resolved = $this->labPriceResolver->resolveWithLabFallback(
                $doctor,
                $treatment,
                $activeLabs,
                $workDate,
            );

            if ($resolved === null) {
                continue;
            }

            $unitInClinicCurrency = ClinicCurrencySupport::toClinicCurrency(
                (string) $resolved['price']->unit_cost,
                $resolved['price']->currency,
                $clinicCurrency,
            );
            $lineTotal = MoneyCalculator::multiply($unitInClinicCurrency, (int) $item->quantity);
            $total = MoneyCalculator::add($total, $lineTotal);
        }

        return $total;
    }

    /**
     * @param  array<int, array{code: string, quantity: int}>  $treatmentLines
     */
    private function estimateDoctorIncome(
        Doctor $doctor,
        array $treatmentLines,
        string $paidTotal,
        string $labTotal,
        string $usdPaid,
        string $clinicCurrency,
    ): string {
        if ($doctor->commission_type === CommissionType::Fixed) {
            $doctor->loadMissing('doctorFixedFees.treatment');
            $total = '0.00';

            foreach ($treatmentLines as $line) {
                $code = strtoupper(trim((string) ($line['code'] ?? '')));
                $qty = (int) ($line['quantity'] ?? 0);
                $fee = $doctor->doctorFixedFees->first(fn($row) => $row->treatment?->code === $code);

                if ($fee === null || $qty < 1 || ! in_array($code, WaelFixedFeeCalculator::BILLABLE_CODES, true)) {
                    continue;
                }

                $lineFee = MoneyCalculator::multiply((string) $fee->fee_amount, (string) $qty);

                if ($code === 'IMPL') {
                    $total = MoneyCalculator::add($total, $lineFee);

                    continue;
                }

                $total = MoneyCalculator::add(
                    $total,
                    MoneyCalculator::convertBetween($lineFee, $fee->currency, $clinicCurrency),
                );
            }

            return $total;
        }

        $net = MoneyCalculator::subtract($paidTotal, $labTotal);
        $pct = (string) ($doctor->commission_percentage ?? '0');

        return MoneyCalculator::percentage($net, $pct);
    }

    private function nextManualRowNumber(DailyReport $dailyReport, int $doctorId, int $day): int
    {
        $max = AccountingScopedQuery::workRows((int) $dailyReport->clinic_id, $dailyReport->id)
            ->where('doctor_id', $doctorId)
            ->whereDate('work_date', Carbon::parse($dailyReport->report_date)->startOfMonth()->day($day))
            ->max('excel_row_number');

        return max(1, (int) $max + 1);
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
