<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicFinancialOverviewRequest;
use App\Services\Analytics\ClinicFinancialOverviewService;
use Illuminate\View\View;

/**
 * Practice financial overview for the current clinic (ADR-036).
 */
class ClinicFinancialOverviewController extends Controller
{
    public function __invoke(
        ClinicFinancialOverviewRequest $request,
        ClinicFinancialOverviewService $overviewService,
    ): View {
        $overview = $overviewService->build($request->validated('month'));

        return view('clinic-financial-overview.index', [
            'overview' => $overview,
            'selectedMonth' => $overview->selectedMonth,
        ]);
    }
}
