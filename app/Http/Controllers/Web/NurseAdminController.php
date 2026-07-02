<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\PreservesConfigurationReturn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nurses\ListNursesRequest;
use App\Http\Requests\Nurses\StoreNurseRequest;
use App\Http\Requests\Nurses\UpdateNurseRequest;
use App\Models\Nurse;
use App\Services\Accounting\TreatmentManagementService;
use App\Services\Configuration\NurseManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only nurse master data.
 */
class NurseAdminController extends Controller
{
    use PreservesConfigurationReturn;

    private const PER_PAGE = 20;

    public function __construct(
        private readonly NurseManagementService $nurseManagementService,
        private readonly TreatmentManagementService $treatmentManagementService,
    ) {}

    public function index(ListNursesRequest $request): View
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'all';

        $nurses = $this->nurseManagementService->listQuery($search, $status)
            ->with(['nurseCommissionRates.treatment'])
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('nurses.index', [
            'nurses' => $nurses,
            'search' => $search,
            'status' => $status,
            'commissionTreatments' => $this->treatmentManagementService->listActiveRequiringNurseCommission(),
            'showConfigurationBack' => $this->showConfigurationBack($request),
        ]);
    }

    public function store(StoreNurseRequest $request): RedirectResponse
    {
        $nurse = $this->nurseManagementService->create($request->validated());

        return redirect()
            ->route('nurses.index', $this->filterRedirectParams($request))
            ->with('success', "Nurse {$nurse->code} created.");
    }

    public function update(UpdateNurseRequest $request, Nurse $nurse): RedirectResponse
    {
        $this->nurseManagementService->update($nurse, $request->validated());

        return redirect()
            ->route('nurses.index', $this->filterRedirectParams($request))
            ->with('success', "Nurse {$nurse->code} updated.");
    }

    public function destroy(Request $request, Nurse $nurse): RedirectResponse
    {
        $code = $nurse->code;

        $this->nurseManagementService->deactivate($nurse);

        return redirect()
            ->route('nurses.index', $this->mergeConfigurationReturn($request, $request->only(['search', 'status', 'page'])))
            ->with('success', "Nurse {$code} deactivated.");
    }

    public function activate(Request $request, Nurse $nurse): RedirectResponse
    {
        $this->nurseManagementService->activate($nurse);

        return redirect()
            ->route('nurses.index', $this->mergeConfigurationReturn($request, $request->only(['search', 'status', 'page'])))
            ->with('success', "Nurse {$nurse->code} activated.");
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
