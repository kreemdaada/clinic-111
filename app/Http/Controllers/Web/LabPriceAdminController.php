<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabPrices\ListLabPricesRequest;
use App\Http\Requests\LabPrices\StoreLabPriceRequest;
use App\Http\Requests\LabPrices\UpdateLabPriceRequest;
use App\Models\LabPrice;
use App\Services\Accounting\LabManagementService;
use App\Services\Accounting\LabPriceManagementService;
use App\Services\Accounting\TreatmentManagementService;
use App\Services\DailyReport\DoctorManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only lab price master data.
 */
class LabPriceAdminController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly LabPriceManagementService $labPriceManagementService,
        private readonly LabManagementService $labManagementService,
        private readonly TreatmentManagementService $treatmentManagementService,
        private readonly DoctorManagementService $doctorManagementService,
    ) {}

    public function index(ListLabPricesRequest $request): View
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $labId = isset($validated['lab_id']) ? (int) $validated['lab_id'] : null;
        $treatmentId = isset($validated['treatment_id']) ? (int) $validated['treatment_id'] : null;
        $doctorFilter = $validated['doctor_id'] ?? 'all';
        $status = $validated['status'] ?? 'all';
        $currency = isset($validated['currency']) ? strtoupper($validated['currency']) : null;

        $prices = $this->labPriceManagementService->listQuery($search, $labId, $treatmentId, $doctorFilter, $status, $currency)
            ->with(['lab', 'treatment', 'doctor'])
            ->orderByDesc('is_active')
            ->orderBy('lab_id')
            ->orderBy('treatment_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('lab-prices.index', [
            'prices' => $prices,
            'labs' => $this->labManagementService->listQuery()->orderBy('name')->get(),
            'treatments' => $this->treatmentManagementService->listActiveWithLabCost(),
            'doctors' => $this->doctorManagementService->listActive(),
            'search' => $search,
            'labId' => $labId,
            'treatmentId' => $treatmentId,
            'doctorFilter' => $doctorFilter,
            'status' => $status,
            'currency' => $currency,
        ]);
    }

    public function store(StoreLabPriceRequest $request): RedirectResponse
    {
        $price = $this->labPriceManagementService->create($request->validated());

        return redirect()
            ->route('lab-prices.index', $this->filterRedirectParams($request))
            ->with('success', "Lab price #{$price->id} created.");
    }

    public function update(UpdateLabPriceRequest $request, LabPrice $labPrice): RedirectResponse
    {
        $this->labPriceManagementService->update($labPrice, $request->validated());

        return redirect()
            ->route('lab-prices.index', $this->filterRedirectParams($request))
            ->with('success', "Lab price #{$labPrice->id} updated.");
    }

    public function destroy(LabPrice $labPrice): RedirectResponse
    {
        $id = $labPrice->id;

        $this->labPriceManagementService->deactivate($labPrice);

        return redirect()
            ->route('lab-prices.index')
            ->with('success', "Lab price #{$id} deleted.");
    }

    public function activate(LabPrice $labPrice): RedirectResponse
    {
        $this->labPriceManagementService->activate($labPrice);

        return redirect()
            ->route('lab-prices.index')
            ->with('success', "Lab price #{$labPrice->id} activated.");
    }

    public function duplicate(LabPrice $labPrice): RedirectResponse
    {
        $copy = $this->labPriceManagementService->duplicate($labPrice);

        return redirect()
            ->route('lab-prices.index')
            ->with('success', "Lab price duplicated as #{$copy->id} (inactive). Adjust dates and activate when ready.");
    }

    /**
     * @return array<string, mixed>
     */
    private function filterRedirectParams(Request $request): array
    {
        return array_filter([
            'search' => $request->input('return_search'),
            'lab_id' => $request->input('return_lab_id'),
            'treatment_id' => $request->input('return_treatment_id'),
            'doctor_id' => $request->input('return_doctor_id'),
            'status' => $request->input('return_status'),
            'currency' => $request->input('return_currency'),
            'page' => $request->input('return_page'),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
