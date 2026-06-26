<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorFixedFees\ListDoctorFixedFeesRequest;
use App\Http\Requests\DoctorFixedFees\StoreDoctorFixedFeeRequest;
use App\Http\Requests\DoctorFixedFees\UpdateDoctorFixedFeeRequest;
use App\Models\DoctorFixedFee;
use App\Services\Accounting\DoctorFixedFeeManagementService;
use Illuminate\Http\JsonResponse;

/**
 * Admin API for doctor fixed fee master data.
 */
class DoctorFixedFeeAdminController extends Controller
{
    public function __construct(
        private readonly DoctorFixedFeeManagementService $doctorFixedFeeManagementService,
    ) {}

    public function index(ListDoctorFixedFeesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $doctorId = isset($validated['doctor_id']) ? (int) $validated['doctor_id'] : null;
        $treatmentId = isset($validated['treatment_id']) ? (int) $validated['treatment_id'] : null;
        $status = $validated['status'] ?? 'all';
        $currency = isset($validated['currency']) ? strtoupper($validated['currency']) : null;

        $fees = $this->doctorFixedFeeManagementService->listQuery($search, $doctorId, $treatmentId, $status, $currency)
            ->with(['doctor', 'treatment'])
            ->orderByDesc('is_active')
            ->orderBy('doctor_id')
            ->orderBy('treatment_id')
            ->get()
            ->map(fn (DoctorFixedFee $fee) => $this->formatDoctorFixedFee($fee));

        return response()->json(['data' => $fees]);
    }

    public function store(StoreDoctorFixedFeeRequest $request): JsonResponse
    {
        $fee = $this->doctorFixedFeeManagementService->create($request->validated());

        return response()->json([
            'message' => 'Doctor fixed fee created.',
            'data' => $this->formatDoctorFixedFee($fee),
        ], 201);
    }

    public function update(UpdateDoctorFixedFeeRequest $request, DoctorFixedFee $doctorFixedFee): JsonResponse
    {
        $fee = $this->doctorFixedFeeManagementService->update($doctorFixedFee, $request->validated());

        return response()->json([
            'message' => 'Doctor fixed fee updated.',
            'data' => $this->formatDoctorFixedFee($fee),
        ]);
    }

    public function destroy(DoctorFixedFee $doctorFixedFee): JsonResponse
    {
        $fee = $this->doctorFixedFeeManagementService->deactivate($doctorFixedFee);

        return response()->json([
            'message' => 'Doctor fixed fee deactivated.',
            'data' => $this->formatDoctorFixedFee($fee),
        ]);
    }

    public function activate(DoctorFixedFee $doctorFixedFee): JsonResponse
    {
        $fee = $this->doctorFixedFeeManagementService->activate($doctorFixedFee);

        return response()->json([
            'message' => 'Doctor fixed fee activated.',
            'data' => $this->formatDoctorFixedFee($fee),
        ]);
    }

    public function duplicate(DoctorFixedFee $doctorFixedFee): JsonResponse
    {
        $fee = $this->doctorFixedFeeManagementService->duplicate($doctorFixedFee);

        return response()->json([
            'message' => 'Doctor fixed fee duplicated (inactive).',
            'data' => $this->formatDoctorFixedFee($fee),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDoctorFixedFee(DoctorFixedFee $doctorFixedFee): array
    {
        return [
            'id' => $doctorFixedFee->id,
            'doctor_id' => $doctorFixedFee->doctor_id,
            'doctor_code' => $doctorFixedFee->doctor?->code,
            'treatment_id' => $doctorFixedFee->treatment_id,
            'treatment_code' => $doctorFixedFee->treatment?->code,
            'fee_amount' => (string) $doctorFixedFee->fee_amount,
            'currency' => $doctorFixedFee->currency,
            'valid_from' => $doctorFixedFee->valid_from?->toDateString(),
            'valid_to' => $doctorFixedFee->valid_to?->toDateString(),
            'is_active' => $doctorFixedFee->is_active,
        ];
    }
}
