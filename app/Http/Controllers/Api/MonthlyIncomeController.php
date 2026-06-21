<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlyIncome\MonthlyIncomeRequest;
use App\Services\Accounting\MonthlyIncomeCalculationService;
use Illuminate\Http\JsonResponse;

/**
 * On-demand monthly income summary API per doctor.
 *
 * Route: GET /api/monthly-income?month=YYYY-MM
 */
class MonthlyIncomeController extends Controller
{
    /**
     * Calculate and return income summaries for all active doctors in one month.
     *
     * @param  MonthlyIncomeRequest  $request  Validated `month` query param (`YYYY-MM`).
     * @param  MonthlyIncomeCalculationService  $calculationService  Aggregates payments, JOB, commission.
     * @return JsonResponse `{ month, data: [ MonthlyIncomeSummaryDto… ] }`
     */
    public function index(
        MonthlyIncomeRequest $request,
        MonthlyIncomeCalculationService $calculationService,
    ): JsonResponse {
        $summaries = $calculationService
            ->calculateForMonth($request->validated('month'))
            ->map(fn ($summary) => $summary->toArray())
            ->values();

        return response()->json([
            'month' => $request->validated('month'),
            'data' => $summaries,
        ]);
    }
}
