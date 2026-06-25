<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    /**
     * @return array<string, mixed>
     */
    private function formatLabPrice(LabPrice $labPrice): array
    {
        return [
            'id' => $labPrice->id,
            'lab_id' => $labPrice->lab_id,
            'treatment_id' => $labPrice->treatment_id,
            'doctor_id' => $labPrice->doctor_id,
            'unit_cost' => (string) $labPrice->unit_cost,
            'currency' => $labPrice->currency,
            'is_active' => $labPrice->is_active,
        ];
    }
}
