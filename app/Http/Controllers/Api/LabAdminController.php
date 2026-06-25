<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Labs\ListLabsRequest;
use App\Http\Requests\Labs\StoreLabRequest;
use App\Http\Requests\Labs\UpdateLabRequest;
use App\Models\Lab;
use App\Services\Accounting\LabManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Admin API for laboratory master data.
 */
class LabAdminController extends Controller
{
    public function __construct(
        private readonly LabManagementService $labManagementService,
    ) {}

    public function index(ListLabsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'all';

        $labs = $this->filteredLabsQuery($search, $status)
            ->withCount(['labJobs', 'labPrices'])
            ->orderBy('name')
            ->get()
            ->map(fn (Lab $lab) => $this->formatLab($lab));

        return response()->json(['data' => $labs]);
    }

    public function store(StoreLabRequest $request): JsonResponse
    {
        $lab = $this->labManagementService->create($request->validated());

        return response()->json([
            'message' => 'Laboratory created.',
            'data' => $this->formatLab($lab),
        ], 201);
    }

    public function update(UpdateLabRequest $request, Lab $lab): JsonResponse
    {
        $lab = $this->labManagementService->update($lab, $request->validated());

        return response()->json([
            'message' => 'Laboratory updated.',
            'data' => $this->formatLab($lab),
        ]);
    }

    public function destroy(Lab $lab): JsonResponse
    {
        $lab = $this->labManagementService->deactivate($lab);

        return response()->json([
            'message' => 'Laboratory deactivated.',
            'data' => $this->formatLab($lab),
        ]);
    }

    public function activate(Lab $lab): JsonResponse
    {
        $lab = $this->labManagementService->activate($lab);

        return response()->json([
            'message' => 'Laboratory activated.',
            'data' => $this->formatLab($lab),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLab(Lab $lab): array
    {
        return [
            'id' => $lab->id,
            'name' => $lab->name,
            'code' => $lab->code,
            'is_active' => $lab->is_active,
            'lab_jobs_count' => $lab->lab_jobs_count ?? null,
            'lab_prices_count' => $lab->lab_prices_count ?? null,
        ];
    }

    private function filteredLabsQuery(?string $search, string $status): Builder
    {
        $query = Lab::query();

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
    }
}
