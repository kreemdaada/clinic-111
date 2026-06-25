<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabPrices\ListLabPricesRequest;
use App\Http\Requests\LabPrices\StoreLabPriceRequest;
use App\Http\Requests\LabPrices\UpdateLabPriceRequest;
use App\Models\LabPrice;
use App\Services\Accounting\LabPriceManagementService;
use Illuminate\Database\Eloquent\Builder;
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

        $prices = $this->filteredPricesQuery($search, $labId, $treatmentId, $doctorFilter, $status, $currency)
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
