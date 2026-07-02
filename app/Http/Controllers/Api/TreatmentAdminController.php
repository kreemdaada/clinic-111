<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Treatments\ListTreatmentsRequest;
use App\Http\Requests\Treatments\StoreTreatmentRequest;
use App\Http\Requests\Treatments\UpdateTreatmentRequest;
use App\Models\Treatment;
use App\Services\Accounting\TreatmentManagementService;
use Illuminate\Http\JsonResponse;

/**
 * Admin API for treatment master data.
 */
class TreatmentAdminController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly TreatmentManagementService $treatmentManagementService,
    ) {}

    public function index(ListTreatmentsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'all';

        $treatments = $this->treatmentManagementService->listQuery($search, $status)
            ->withCount('workItems')
            ->orderBy('code')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'data' => collect($treatments->items())->map(fn (Treatment $treatment) => $this->formatTreatment($treatment)),
            'meta' => [
                'current_page' => $treatments->currentPage(),
                'last_page' => $treatments->lastPage(),
                'per_page' => $treatments->perPage(),
                'total' => $treatments->total(),
            ],
        ]);
    }

    public function store(StoreTreatmentRequest $request): JsonResponse
    {
        $treatment = $this->treatmentManagementService->create($request->validated());

        return response()->json([
            'message' => 'Treatment created.',
            'data' => $this->formatTreatment($treatment),
        ], 201);
    }

    public function update(UpdateTreatmentRequest $request, Treatment $treatment): JsonResponse
    {
        $treatment = $this->treatmentManagementService->update($treatment, $request->validated());

        return response()->json([
            'message' => 'Treatment updated.',
            'data' => $this->formatTreatment($treatment),
        ]);
    }

    public function destroy(Treatment $treatment): JsonResponse
    {
        $treatment = $this->treatmentManagementService->deactivate($treatment);

        return response()->json([
            'message' => 'Treatment deactivated.',
            'data' => $this->formatTreatment($treatment),
        ]);
    }

    public function activate(Treatment $treatment): JsonResponse
    {
        $treatment = $this->treatmentManagementService->activate($treatment);

        return response()->json([
            'message' => 'Treatment activated.',
            'data' => $this->formatTreatment($treatment),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTreatment(Treatment $treatment): array
    {
        return [
            'id' => $treatment->id,
            'code' => $treatment->code,
            'name' => $treatment->name,
            'description' => $treatment->description,
            'has_lab_cost' => $treatment->has_lab_cost,
            'treatment_price' => $treatment->treatment_price !== null ? (string) $treatment->treatment_price : null,
            'treatment_price_currency' => $treatment->treatment_price_currency,
            'requires_nurse_commission' => $treatment->requires_nurse_commission,
            'is_active' => $treatment->is_active,
            'work_items_count' => $treatment->work_items_count ?? null,
        ];
    }
}
