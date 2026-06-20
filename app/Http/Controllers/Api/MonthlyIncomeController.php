<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MonthlyIncome\MonthlyIncomeRequest;
use App\Services\Accounting\MonthlyIncomeCalculationService;
use Illuminate\Http\JsonResponse;

class MonthlyIncomeController extends Controller
{
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
