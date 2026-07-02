<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nurses\ListNursesRequest;
use App\Http\Requests\Nurses\StoreNurseRequest;
use App\Http\Requests\Nurses\UpdateNurseRequest;
use App\Models\Nurse;
use App\Services\Configuration\NurseManagementService;
use Illuminate\Http\JsonResponse;

/**
 * Admin API for nurse master data.
 */
class NurseAdminController extends Controller
{
    public function __construct(
        private readonly NurseManagementService $nurseManagementService,
    ) {}

    public function index(ListNursesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'all';

        $nurses = $this->nurseManagementService->listQuery($search, $status)
            ->orderBy('name')
            ->get()
            ->map(fn (Nurse $nurse) => $this->formatNurse($nurse));

        return response()->json(['data' => $nurses]);
    }

    public function store(StoreNurseRequest $request): JsonResponse
    {
        $nurse = $this->nurseManagementService->create($request->validated());

        return response()->json([
            'message' => 'Nurse created.',
            'data' => $this->formatNurse($nurse),
        ], 201);
    }

    public function update(UpdateNurseRequest $request, Nurse $nurse): JsonResponse
    {
        $nurse = $this->nurseManagementService->update($nurse, $request->validated());

        return response()->json([
            'message' => 'Nurse updated.',
            'data' => $this->formatNurse($nurse),
        ]);
    }

    public function destroy(Nurse $nurse): JsonResponse
    {
        $nurse = $this->nurseManagementService->deactivate($nurse);

        return response()->json([
            'message' => 'Nurse deactivated.',
            'data' => $this->formatNurse($nurse),
        ]);
    }

    public function activate(Nurse $nurse): JsonResponse
    {
        $nurse = $this->nurseManagementService->activate($nurse);

        return response()->json([
            'message' => 'Nurse activated.',
            'data' => $this->formatNurse($nurse),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatNurse(Nurse $nurse): array
    {
        return [
            'id' => $nurse->id,
            'code' => $nurse->code,
            'name' => $nurse->name,
            'is_active' => $nurse->is_active,
            'created_at' => $nurse->created_at?->toIso8601String(),
            'updated_at' => $nurse->updated_at?->toIso8601String(),
        ];
    }
}
