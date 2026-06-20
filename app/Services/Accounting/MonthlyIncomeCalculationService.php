<?php

namespace App\Services\Accounting;

use App\DTOs\MonthlyIncomeSummaryDto;
use App\Enums\CommissionType;
use App\Enums\PaymentMethod;
use App\Models\Doctor;
use App\Models\LabJob;
use App\Models\Payment;
use App\Models\WorkItem;
use App\Support\MoneyCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calculates monthly income summaries per doctor.
 *
 * TOTAL = SUM(payments.amount_aed)
 * LAB_COST = SUM(lab_jobs.total_cost_aed)
 * NET_TOTAL = TOTAL - LAB_COST
 * DOCTOR_INCOME (percentage) = NET_TOTAL × commission_percentage / 100
 * DOCTOR_INCOME (fixed) = SUM(fixed_fee × quantity)
 * CLINIC_INCOME = NET_TOTAL - DOCTOR_INCOME
 */
class MonthlyIncomeCalculationService
{
    public function __construct(
        private readonly string $defaultUsdExchangeRate = '3.65',
    ) {}

    /**
     * @return Collection<int, MonthlyIncomeSummaryDto>
     */
    public function calculateForMonth(string $month): Collection
    {
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        return Doctor::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Doctor $doctor) => $this->calculateForDoctor($doctor, $monthStart, $monthEnd, $month));
    }

    public function calculateForDoctor(
        Doctor $doctor,
        Carbon $monthStart,
        Carbon $monthEnd,
        ?string $monthLabel = null,
    ): MonthlyIncomeSummaryDto {
        if ($monthLabel === null) {
            $monthLabel = $monthStart->format('Y-m');
        }

        $payments = Payment::query()
            ->whereHas('dailyWorkRow', fn ($query) => $query->where('doctor_id', $doctor->id))
            ->whereBetween('paid_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get();

        $totalDhs = $this->sumPaymentsByMethod($payments, PaymentMethod::Dhs);
        $totalUsdToAed = $this->sumPaymentsByMethod($payments, PaymentMethod::Usd);
        $totalVisa = $this->sumPaymentsByMethod($payments, PaymentMethod::Visa);
        $totalCollectedAed = $this->sumAmountAed($payments);

        $labCostAed = $this->calculateLabCostForDoctor($doctor, $monthStart, $monthEnd);
        $netTotalAed = MoneyCalculator::subtract($totalCollectedAed, $labCostAed);
        $doctorIncomeAed = $this->calculateDoctorIncome(
            $doctor,
            $netTotalAed,
            $monthStart,
            $monthEnd,
        );
        $clinicIncomeAed = MoneyCalculator::subtract($netTotalAed, $doctorIncomeAed);

        return new MonthlyIncomeSummaryDto(
            doctorId: $doctor->id,
            doctorName: $doctor->name,
            month: $monthLabel,
            totalDhs: $totalDhs,
            totalUsdToAed: $totalUsdToAed,
            totalVisa: $totalVisa,
            totalCollectedAed: $totalCollectedAed,
            labCostAed: $labCostAed,
            netTotalAed: $netTotalAed,
            doctorIncomeAed: $doctorIncomeAed,
            clinicIncomeAed: $clinicIncomeAed,
            treatmentCounts: $this->calculateTreatmentCounts($doctor, $monthStart, $monthEnd),
        );
    }

    public function calculateNetTotal(string $totalCollectedAed, string $labCostAed): string
    {
        return MoneyCalculator::subtract($totalCollectedAed, $labCostAed);
    }

    public function calculatePercentageDoctorIncome(string $netTotalAed, string $commissionPercentage): string
    {
        return MoneyCalculator::percentage($netTotalAed, $commissionPercentage);
    }

    private function calculateDoctorIncome(
        Doctor $doctor,
        string $netTotalAed,
        Carbon $monthStart,
        Carbon $monthEnd,
    ): string {
        if ($doctor->commission_type === CommissionType::Percentage) {
            $commissionPercentage = '0';
            if ($doctor->commission_percentage !== null) {
                $commissionPercentage = (string) $doctor->commission_percentage;
            }

            return $this->calculatePercentageDoctorIncome(
                $netTotalAed,
                $commissionPercentage,
            );
        }

        return $this->calculateFixedDoctorIncome($doctor, $monthStart, $monthEnd);
    }

    private function calculateFixedDoctorIncome(Doctor $doctor, Carbon $monthStart, Carbon $monthEnd): string
    {
        $doctor->loadMissing('doctorFixedFees.treatment');

        $workItems = WorkItem::query()
            ->whereHas('dailyWorkRow', function ($query) use ($doctor, $monthStart, $monthEnd) {
                $query
                    ->where('doctor_id', $doctor->id)
                    ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            })
            ->with('treatment')
            ->get();

        $fixedFeesByTreatmentId = $doctor->doctorFixedFees->keyBy('treatment_id');
        $totalIncome = '0.00';

        foreach ($workItems as $workItem) {
            $fixedFee = $fixedFeesByTreatmentId->get($workItem->treatment_id);

            if ($fixedFee === null) {
                continue;
            }

            $feeAmountAed = MoneyCalculator::convertToAed(
                (string) $fixedFee->fee_amount,
                $fixedFee->currency,
                $this->defaultUsdExchangeRate,
            );

            $totalIncome = MoneyCalculator::add(
                $totalIncome,
                MoneyCalculator::multiply($feeAmountAed, $workItem->quantity),
            );
        }

        return $totalIncome;
    }

    private function calculateLabCostForDoctor(Doctor $doctor, Carbon $monthStart, Carbon $monthEnd): string
    {
        $labJobs = LabJob::query()
            ->whereHas('workItem.dailyWorkRow', function ($query) use ($doctor, $monthStart, $monthEnd) {
                $query
                    ->where('doctor_id', $doctor->id)
                    ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            })
            ->get();

        return $this->sumAmountAed($labJobs, 'total_cost_aed');
    }

    /**
     * @return array<string, int>
     */
    private function calculateTreatmentCounts(Doctor $doctor, Carbon $monthStart, Carbon $monthEnd): array
    {
        $workItems = WorkItem::query()
            ->whereHas('dailyWorkRow', function ($query) use ($doctor, $monthStart, $monthEnd) {
                $query
                    ->where('doctor_id', $doctor->id)
                    ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            })
            ->with('treatment')
            ->get();

        $counts = [];

        foreach ($workItems as $workItem) {
            $code = $workItem->treatment->code;

            if (! array_key_exists($code, $counts)) {
                $counts[$code] = 0;
            }

            $counts[$code] += $workItem->quantity;
        }

        ksort($counts);

        return $counts;
    }

    private function sumPaymentsByMethod(Collection $payments, PaymentMethod $method): string
    {
        $filtered = $payments->where('payment_method', $method);

        return $this->sumAmountAed($filtered);
    }

    private function sumAmountAed(Collection $records, string $amountField = 'amount_aed'): string
    {
        $total = '0.00';

        foreach ($records as $record) {
            $total = MoneyCalculator::add($total, (string) $record->{$amountField});
        }

        return $total;
    }
}
