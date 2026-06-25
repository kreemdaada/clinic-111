<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabPrices\StoreLabPriceRequest;
use App\Http\Requests\LabPrices\UpdateLabPriceRequest;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Services\Accounting\LabPriceManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin-only lab price master data.
 */
class LabPriceAdminController extends Controller
{
    public function __construct(
        private readonly LabPriceManagementService $labPriceManagementService,
    ) {}

    public function index(): View
    {
        $prices = LabPrice::query()
            ->with(['lab', 'treatment', 'doctor'])
            ->orderByDesc('is_active')
            ->orderBy('lab_id')
            ->orderBy('treatment_id')
            ->get();

        return view('lab-prices.index', [
            'prices' => $prices,
            'labs' => Lab::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreLabPriceRequest $request): RedirectResponse
    {
        $this->labPriceManagementService->create($request->validated());

        return redirect()
            ->route('lab-prices.index')
            ->with('success', 'Lab price created.');
    }

    public function update(UpdateLabPriceRequest $request, LabPrice $labPrice): RedirectResponse
    {
        $this->labPriceManagementService->update($labPrice, $request->validated());

        return redirect()
            ->route('lab-prices.index')
            ->with('success', 'Lab price updated.');
    }

    public function destroy(LabPrice $labPrice): RedirectResponse
    {
        $this->labPriceManagementService->deactivate($labPrice);

        return redirect()
            ->route('lab-prices.index')
            ->with('success', 'Lab price deactivated.');
    }
}
