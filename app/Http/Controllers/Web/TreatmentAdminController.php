<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\PreservesConfigurationReturn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Treatments\ListTreatmentsRequest;
use App\Http\Requests\Treatments\StoreTreatmentRequest;
use App\Http\Requests\Treatments\UpdateTreatmentRequest;
use App\Models\Treatment;
use App\Services\Accounting\TreatmentManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only treatment master data.
 */
class TreatmentAdminController extends Controller
{
    use PreservesConfigurationReturn;

    private const PER_PAGE = 20;

    public function __construct(
        private readonly TreatmentManagementService $treatmentManagementService,
    ) {}

    public function index(ListTreatmentsRequest $request): View
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'all';

        $treatments = $this->treatmentManagementService->listQuery($search, $status)
            ->withCount('workItems')
            ->orderBy('code')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('treatments.index', [
            'treatments' => $treatments,
            'search' => $search,
            'status' => $status,
            'showConfigurationBack' => $this->showConfigurationBack($request),
        ]);
    }

    public function store(StoreTreatmentRequest $request): RedirectResponse
    {
        $treatment = $this->treatmentManagementService->create($request->validated());

        return redirect()
            ->route('treatments.index', $this->filterRedirectParams($request))
            ->with('success', "Treatment {$treatment->code} created.");
    }

    public function update(UpdateTreatmentRequest $request, Treatment $treatment): RedirectResponse
    {
        $this->treatmentManagementService->update($treatment, $request->validated());

        return redirect()
            ->route('treatments.index', $this->filterRedirectParams($request))
            ->with('success', "Treatment {$treatment->code} updated.");
    }

    public function destroy(Request $request, Treatment $treatment): RedirectResponse
    {
        $code = $treatment->code;

        $this->treatmentManagementService->deactivate($treatment);

        return redirect()
            ->route('treatments.index', $this->mergeConfigurationReturn($request))
            ->with('success', "Treatment {$code} deleted.");
    }

    public function activate(Request $request, Treatment $treatment): RedirectResponse
    {
        $this->treatmentManagementService->activate($treatment);

        return redirect()
            ->route('treatments.index', $this->mergeConfigurationReturn($request))
            ->with('success', "Treatment {$treatment->code} activated.");
    }

    /**
     * @return array<string, mixed>
     */
    private function filterRedirectParams(Request $request): array
    {
        return $this->mergeConfigurationReturn($request, array_filter([
            'search' => $request->input('return_search'),
            'status' => $request->input('return_status'),
            'page' => $request->input('return_page'),
        ], fn ($value) => $value !== null && $value !== ''));
    }
}
