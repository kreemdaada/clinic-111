<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabPrices\ListLabPricesRequest;
use App\Http\Requests\LabPrices\StoreLabPriceRequest;
use App\Http\Requests\LabPrices\UpdateLabPriceRequest;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Services\Accounting\LabPriceManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin-only lab price master data.
 */
class LabPriceAdminController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly LabPriceManagementService $labPriceManagementService,
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

        $prices = $this->filteredPricesQuery($search, $labId, $treatmentId, $doctorFilter, $status, $currency)
            ->with(['lab', 'treatment', 'doctor'])
            ->orderByDesc('is_active')
            ->orderBy('lab_id')
            ->orderBy('treatment_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('lab-prices.index', [
            'prices' => $prices,
            'labs' => Lab::query()->orderBy('name')->get(),
            'treatments' => Treatment::query()->where('has_lab_cost', true)->orderBy('code')->get(),
            'doctors' => Doctor::query()->orderBy('code')->get(),
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
            ->route('lab-prices.index', $request->only([
                'search', 'lab_id', 'treatment_id', 'doctor_id', 'status', 'currency',
            ]))
            ->with('success', "Lab price #{$price->id} created.");
    }

    public function update(UpdateLabPriceRequest $request, LabPrice $labPrice): RedirectResponse
    {
        $this->labPriceManagementService->update($labPrice, $request->validated());

        return redirect()
            ->route('lab-prices.index', $request->only([
                'search', 'lab_id', 'treatment_id', 'doctor_id', 'status', 'currency', 'page',
            ]))
            ->with('success', "Lab price #{$labPrice->id} updated.");
    }

    public function destroy(LabPrice $labPrice): RedirectResponse
    {
        $id = $labPrice->id;

        $this->labPriceManagementService->deactivate($labPrice);

        return redirect()
            ->route('lab-prices.index')
            ->with('success', "Lab price #{$id} deactivated.");
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

    private function filteredPricesQuery(
        ?string $search,
        ?int $labId,
        ?int $treatmentId,
        string $doctorFilter,
        string $status,
        ?string $currency,
    ): Builder {
        $query = LabPrice::query();

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->whereHas('lab', fn (Builder $q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term))
                    ->orWhereHas('treatment', fn (Builder $q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term))
                    ->orWhereHas('doctor', fn (Builder $q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term));
            });
        }

        if ($labId !== null) {
            $query->where('lab_id', $labId);
        }

        if ($treatmentId !== null) {
            $query->where('treatment_id', $treatmentId);
        }

        if ($doctorFilter === 'general') {
            $query->whereNull('doctor_id');
        } elseif ($doctorFilter !== 'all' && $doctorFilter !== '') {
            $query->where('doctor_id', (int) $doctorFilter);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($currency !== null && $currency !== '') {
            $query->where('currency', $currency);
        }

        return $query;
    }
}
