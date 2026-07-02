<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlyIncome\WebMonthlyIncomeRequest;
use App\Services\Accounting\MonthlyIncomeCalculationService;
use App\Services\Configuration\CurrentClinicResolver;
use App\Support\Analytics\FinancialPeriod;
use Illuminate\View\View;

/**
 * Monthly income summary per doctor (web UI).
 */
class MonthlyIncomeController extends Controller
{
    public function __invoke(
        WebMonthlyIncomeRequest $request,
        MonthlyIncomeCalculationService $calculationService,
        CurrentClinicResolver $currentClinicResolver,
    ): View {
        $clinic = $currentClinicResolver->resolve();
        $timezone = $clinic->timezone ?: config('app.timezone', 'UTC');
        $month = $request->validated('month')
            ?? FinancialPeriod::currentMonth($timezone)->label;

        $summaries = $calculationService->calculateForMonth($month);

        return view('monthly-income.index', [
            'summaries' => $summaries,
            'selectedMonth' => $month,
        ]);
    }
}
