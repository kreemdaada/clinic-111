<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinics\ListClinicsRequest;
use App\Http\Requests\Clinics\StoreClinicRequest;
use App\Http\Requests\Clinics\UpdateClinicRequest;
use App\Models\Clinic;
use App\Services\Configuration\ClinicManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Admin API for clinic tenant master data.
 */
class ClinicAdminController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly ClinicManagementService $clinicManagementService,
    ) {}

    public function index(ListClinicsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'all';

        $clinics = $this->filteredClinicsQuery($search, $status)
            ->orderBy('name')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'data' => collect($clinics->items())->map(fn (Clinic $clinic) => $this->formatClinic($clinic)),
            'meta' => [
                'current_page' => $clinics->currentPage(),
                'last_page' => $clinics->lastPage(),
                'per_page' => $clinics->perPage(),
                'total' => $clinics->total(),
            ],
        ]);
    }

    public function store(StoreClinicRequest $request): JsonResponse
    {
        $clinic = $this->clinicManagementService->create($request->validated());

        return response()->json([
            'message' => 'Clinic created.',
            'data' => $this->formatClinic($clinic),
        ], 201);
    }

    public function update(UpdateClinicRequest $request, Clinic $clinic): JsonResponse
    {
        $clinic = $this->clinicManagementService->update($clinic, $request->validated());

        return response()->json([
            'message' => 'Clinic updated.',
            'data' => $this->formatClinic($clinic),
        ]);
    }

    public function destroy(Clinic $clinic): JsonResponse
    {
        $clinic = $this->clinicManagementService->deactivate($clinic);

        return response()->json([
            'message' => 'Clinic deactivated.',
            'data' => $this->formatClinic($clinic),
        ]);
    }

    public function activate(Clinic $clinic): JsonResponse
    {
        $clinic = $this->clinicManagementService->activate($clinic);

        return response()->json([
            'message' => 'Clinic activated.',
            'data' => $this->formatClinic($clinic),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatClinic(Clinic $clinic): array
    {
        return [
            'id' => $clinic->id,
            'name' => $clinic->name,
            'code' => $clinic->code,
            'currency' => $clinic->currency,
            'timezone' => $clinic->timezone,
            'country' => $clinic->country,
            'is_active' => $clinic->is_active,
        ];
    }

    private function filteredClinicsQuery(?string $search, string $status): Builder
    {
        $query = Clinic::query();

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('country', 'like', $term);
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
