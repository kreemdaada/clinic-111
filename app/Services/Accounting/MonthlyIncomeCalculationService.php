<?php

namespace App\Services\Accounting;

use App\DTOs\MonthlyIncomeSummaryDto;
use App\Enums\CommissionType;
use App\Enums\PaymentMethod;
use App\Models\Doctor;
use App\Models\LabJob;
use App\Models\Payment;
use App\Models\WorkItem;
use App\Services\Accounting\Concerns\ScopesAccountingQueries;
use App\Services\Accounting\WaelFixedFeeCalculator;
use App\Services\Configuration\CurrentClinicResolver;
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
    use ScopesAccountingQueries;

    /**
     * @param  string  $defaultUsdExchangeRate  USD→AED rate for fixed-fee currency conversion.
     */
    public function __construct(
        private readonly WaelFixedFeeCalculator $waelFixedFeeCalculator,
        private readonly CurrentClinicResolver $currentClinicResolver,
        private readonly string $defaultUsdExchangeRate = '3.65',
    ) {}

    /**
     * Calculate monthly income summaries for all active doctors.
     *
     * @param  string  $month  Month label in `Y-m` format.
     * @return Collection<int, MonthlyIncomeSummaryDto> One summary per active doctor.
     */
    public function calculateForMonth(string $month): Collection
    {
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        return $this->forCurrentClinic(Doctor::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn(Doctor $doctor) => $this->calculateForDoctor($doctor, $monthStart, $monthEnd, $month));
    }

    /**
     * Calculate the full monthly income summary for a single doctor.
     *
     * @param  Doctor  $doctor  Doctor to summarize.
     * @param  Carbon  $monthStart  First day of the month.
     * @param  Carbon  $monthEnd  Last day of the month.
     * @param  string|null  $monthLabel  Display label (defaults to `Y-m` from monthStart).
     * @return MonthlyIncomeSummaryDto Aggregated payments, lab cost, and income split.
     */
    public function calculateForDoctor(
        Doctor $doctor,
        Carbon $monthStart,
        Carbon $monthEnd,
        ?string $monthLabel = null,
    ): MonthlyIncomeSummaryDto {
        if ($monthLabel === null) {
            $monthLabel = $monthStart->format('Y-m');
        }

        $payments = $this->forCurrentClinic(Payment::class)
            ->whereHas('dailyWorkRow', fn($query) => $query->where('doctor_id', $doctor->id))
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

    /**
     * Compute net total as collected payments minus lab cost.
     *
     * @param  string  $totalCollectedAed  Sum of all payments in AED.
     * @param  string  $labCostAed  Sum of lab job costs in AED.
     * @return string Net total with 2 decimal places.
     */
    public function calculateNetTotal(string $totalCollectedAed, string $labCostAed): string
    {
        return MoneyCalculator::subtract($totalCollectedAed, $labCostAed);
    }

    /**
     * Compute doctor income as a percentage of net total.
     *
     * @param  string  $netTotalAed  Net collected amount after lab cost.
     * @param  string  $commissionPercentage  Percent value (e.g. `35` for 35%).
     * @return string Doctor share with 2 decimal places.
     */
    public function calculatePercentageDoctorIncome(string $netTotalAed, string $commissionPercentage): string
    {
        return MoneyCalculator::percentage($netTotalAed, $commissionPercentage);
    }

    /**
     * Compute doctor income using percentage or fixed-fee commission rules.
     *
     * @param  Doctor  $doctor  Doctor with commission_type configured.
     * @param  string  $netTotalAed  Net collected amount after lab cost.
     * @param  Carbon  $monthStart  First day of the month (for fixed-fee path).
     * @param  Carbon  $monthEnd  Last day of the month (for fixed-fee path).
     * @return string Doctor income in AED with 2 decimal places.
     */
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

    /**
     * Sum fixed-fee amounts for all billable work items in the month.
     *
     * @param  Doctor  $doctor  Doctor with doctorFixedFees relation.
     * @param  Carbon  $monthStart  First day of the month.
     * @param  Carbon  $monthEnd  Last day of the month.
     * @return string Total fixed doctor income in AED.
     */
    private function calculateFixedDoctorIncome(Doctor $doctor, Carbon $monthStart, Carbon $monthEnd): string
    {
        $doctor->loadMissing('doctorFixedFees.treatment');

        $workItems = $this->forCurrentClinic(WorkItem::class)
            ->whereHas('dailyWorkRow', function ($query) use ($doctor, $monthStart, $monthEnd) {
                $query
                    ->where('doctor_id', $doctor->id)
                    ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            })
            ->with(['treatment', 'dailyWorkRow'])
            ->get();

        $fixedFeesByTreatmentId = $doctor->doctorFixedFees->keyBy('treatment_id')->all();

        return $this->waelFixedFeeCalculator->sumIncomeAed(
            $workItems,
            $fixedFeesByTreatmentId,
            $this->defaultUsdExchangeRate,
        );
    }

    /**
     * Sum lab job costs for a doctor's work items in the given month.
     *
     * @param  Doctor  $doctor  Doctor whose lab jobs to total.
     * @param  Carbon  $monthStart  First day of the month.
     * @param  Carbon  $monthEnd  Last day of the month.
     * @return string Total lab cost in AED with 2 decimal places.
     */
    private function calculateLabCostForDoctor(Doctor $doctor, Carbon $monthStart, Carbon $monthEnd): string
    {
        $labJobs = $this->forCurrentClinic(LabJob::class)
            ->whereHas('workItem.dailyWorkRow', function ($query) use ($doctor, $monthStart, $monthEnd) {
                $query
                    ->where('doctor_id', $doctor->id)
                    ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            })
            ->get();

        return $this->sumAmountAed($labJobs, 'total_cost_aed');
    }

    /**
     * Count work-item quantities grouped by treatment code for the month.
     *
     * @param  Doctor  $doctor  Doctor whose work items to count.
     * @param  Carbon  $monthStart  First day of the month.
     * @param  Carbon  $monthEnd  Last day of the month.
     * @return array<string, int> Treatment code → total quantity.
     */
    private function calculateTreatmentCounts(Doctor $doctor, Carbon $monthStart, Carbon $monthEnd): array
    {
        $workItems = $this->forCurrentClinic(WorkItem::class)
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

    /**
     * Sum payment amounts filtered by payment method.
     *
     * @param  Collection<int, Payment>  $payments  Payment records for the period.
     * @param  PaymentMethod  $method  DHS, USD, or VISA.
     * @return string Total amount in AED with 2 decimal places.
     */
    private function sumPaymentsByMethod(Collection $payments, PaymentMethod $method): string
    {
        $filtered = $payments->where('payment_method', $method);

        return $this->sumAmountAed($filtered);
    }

    /**
     * Sum a decimal amount field across a collection of records.
     *
     * @param  Collection<int, object>  $records  Models with a decimal amount column.
     * @param  string  $amountField  Property name to sum (default `amount_aed`).
     * @return string Total with 2 decimal places.
     */
    private function sumAmountAed(Collection $records, string $amountField = 'amount_aed'): string
    {
        $total = '0.00';

        foreach ($records as $record) {
            $total = MoneyCalculator::add($total, (string) $record->{$amountField});
        }

        return $total;
    }
}
