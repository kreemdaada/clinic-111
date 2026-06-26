<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorFixedFees\ListDoctorFixedFeesRequest;
use App\Http\Requests\DoctorFixedFees\StoreDoctorFixedFeeRequest;
use App\Http\Requests\DoctorFixedFees\UpdateDoctorFixedFeeRequest;
use App\Models\DoctorFixedFee;
use App\Services\Accounting\DoctorFixedFeeManagementService;
use App\Services\Accounting\TreatmentManagementService;
use App\Services\DailyReport\DoctorManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only doctor fixed fee master data.
 */
class DoctorFixedFeeAdminController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly DoctorFixedFeeManagementService $doctorFixedFeeManagementService,
        private readonly DoctorManagementService $doctorManagementService,
        private readonly TreatmentManagementService $treatmentManagementService,
    ) {}

    public function index(ListDoctorFixedFeesRequest $request): View
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
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('doctor-fixed-fees.index', [
            'fees' => $fees,
            'doctors' => $this->doctorManagementService->listFixedCommissionDoctors(),
            'treatments' => $this->treatmentManagementService->listActive(),
            'search' => $search,
            'doctorId' => $doctorId,
            'treatmentId' => $treatmentId,
            'status' => $status,
            'currency' => $currency,
        ]);
    }

    public function store(StoreDoctorFixedFeeRequest $request): RedirectResponse
    {
        $fee = $this->doctorFixedFeeManagementService->create($request->validated());

        return redirect()
            ->route('doctor-fixed-fees.index', $this->filterRedirectParams($request))
            ->with('success', "Fee rule #{$fee->id} created.");
    }

    public function update(UpdateDoctorFixedFeeRequest $request, DoctorFixedFee $doctorFixedFee): RedirectResponse
    {
        $this->doctorFixedFeeManagementService->update($doctorFixedFee, $request->validated());

        return redirect()
            ->route('doctor-fixed-fees.index', $this->filterRedirectParams($request))
            ->with('success', "Fee rule #{$doctorFixedFee->id} updated.");
    }

    public function destroy(DoctorFixedFee $doctorFixedFee): RedirectResponse
    {
        $id = $doctorFixedFee->id;

        $this->doctorFixedFeeManagementService->deactivate($doctorFixedFee);

        return redirect()
            ->route('doctor-fixed-fees.index')
            ->with('success', "Fee rule #{$id} deleted.");
    }

    public function activate(DoctorFixedFee $doctorFixedFee): RedirectResponse
    {
        $this->doctorFixedFeeManagementService->activate($doctorFixedFee);

        return redirect()
            ->route('doctor-fixed-fees.index')
            ->with('success', "Fee rule #{$doctorFixedFee->id} activated.");
    }

    public function duplicate(DoctorFixedFee $doctorFixedFee): RedirectResponse
    {
        $copy = $this->doctorFixedFeeManagementService->duplicate($doctorFixedFee);

        return redirect()
            ->route('doctor-fixed-fees.index')
            ->with('success', "Fee rule duplicated as #{$copy->id} (inactive). Adjust dates and activate when ready.");
    }

    /**
     * @return array<string, mixed>
     */
    private function filterRedirectParams(Request $request): array
    {
        return array_filter([
            'search' => $request->input('return_search'),
            'doctor_id' => $request->input('return_doctor_id'),
            'treatment_id' => $request->input('return_treatment_id'),
            'status' => $request->input('return_status'),
            'currency' => $request->input('return_currency'),
            'page' => $request->input('return_page'),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
