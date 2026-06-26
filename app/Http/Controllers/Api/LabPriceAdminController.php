<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabPrices\ListLabPricesRequest;
use App\Http\Requests\LabPrices\StoreLabPriceRequest;
use App\Http\Requests\LabPrices\UpdateLabPriceRequest;
use App\Models\LabPrice;
use App\Services\Accounting\LabPriceManagementService;
use Illuminate\Http\JsonResponse;

/**
 * Admin API for lab price master data.
 */
class LabPriceAdminController extends Controller
{
    public function __construct(
        private readonly LabPriceManagementService $labPriceManagementService,
    ) {}

    public function index(ListLabPricesRequest $request): JsonResponse
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
            ->get()
            ->map(fn (LabPrice $price) => $this->formatLabPrice($price));

        return response()->json(['data' => $prices]);
    }

    public function store(StoreLabPriceRequest $request): JsonResponse
    {
        $price = $this->labPriceManagementService->create($request->validated());

        return response()->json([
            'message' => 'Lab price created.',
            'data' => $this->formatLabPrice($price),
        ], 201);
    }

    public function update(UpdateLabPriceRequest $request, LabPrice $labPrice): JsonResponse
    {
        $price = $this->labPriceManagementService->update($labPrice, $request->validated());

        return response()->json([
            'message' => 'Lab price updated.',
            'data' => $this->formatLabPrice($price),
        ]);
    }

    public function destroy(LabPrice $labPrice): JsonResponse
    {
        $price = $this->labPriceManagementService->deactivate($labPrice);

        return response()->json([
            'message' => 'Lab price deactivated.',
            'data' => $this->formatLabPrice($price),
        ]);
    }

    public function activate(LabPrice $labPrice): JsonResponse
    {
        $price = $this->labPriceManagementService->activate($labPrice);

        return response()->json([
            'message' => 'Lab price activated.',
            'data' => $this->formatLabPrice($price),
        ]);
    }

    public function duplicate(LabPrice $labPrice): JsonResponse
    {
        $price = $this->labPriceManagementService->duplicate($labPrice);

        return response()->json([
            'message' => 'Lab price duplicated (inactive).',
            'data' => $this->formatLabPrice($price),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLabPrice(LabPrice $labPrice): array
    {
        return [
            'id' => $labPrice->id,
            'lab_id' => $labPrice->lab_id,
            'lab_code' => $labPrice->lab?->code,
            'treatment_id' => $labPrice->treatment_id,
            'treatment_code' => $labPrice->treatment?->code,
            'doctor_id' => $labPrice->doctor_id,
            'doctor_code' => $labPrice->doctor?->code,
            'unit_cost' => (string) $labPrice->unit_cost,
            'currency' => $labPrice->currency,
            'valid_from' => $labPrice->valid_from?->toDateString(),
            'valid_to' => $labPrice->valid_to?->toDateString(),
            'is_active' => $labPrice->is_active,
        ];
    }
}
