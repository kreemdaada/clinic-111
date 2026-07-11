<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\PreservesConfigurationReturn;
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
    use PreservesConfigurationReturn;

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
            'showConfigurationBack' => $this->showConfigurationBack($request),
        ]);
    }

    public function store(StoreDoctorFixedFeeRequest $request): RedirectResponse
    {
        $fee = $this->doctorFixedFeeManagementService->create($request->validated());

        return redirect()
            ->route('doctor-fixed-fees.index', $this->filterRedirectParams($request))
            ->with('success', __('configuration.flash.fee_rule_created', ['id' => $fee->id]));
    }

    public function update(UpdateDoctorFixedFeeRequest $request, DoctorFixedFee $doctorFixedFee): RedirectResponse
    {
        $this->doctorFixedFeeManagementService->update($doctorFixedFee, $request->validated());

        return redirect()
            ->route('doctor-fixed-fees.index', $this->filterRedirectParams($request))
            ->with('success', __('configuration.flash.fee_rule_updated', ['id' => $doctorFixedFee->id]));
    }

    public function destroy(Request $request, DoctorFixedFee $doctorFixedFee): RedirectResponse
    {
        $id = $doctorFixedFee->id;

        $this->doctorFixedFeeManagementService->deactivate($doctorFixedFee);

        return redirect()
            ->route('doctor-fixed-fees.index', $this->filterRedirectParams($request))
            ->with('success', __('configuration.flash.fee_rule_deleted', ['id' => $id]));
    }

    public function activate(Request $request, DoctorFixedFee $doctorFixedFee): RedirectResponse
    {
        $this->doctorFixedFeeManagementService->activate($doctorFixedFee);

        return redirect()
            ->route('doctor-fixed-fees.index', $this->filterRedirectParams($request))
            ->with('success', __('configuration.flash.fee_rule_activated', ['id' => $doctorFixedFee->id]));
    }

    public function duplicate(Request $request, DoctorFixedFee $doctorFixedFee): RedirectResponse
    {
        $copy = $this->doctorFixedFeeManagementService->duplicate($doctorFixedFee);

        return redirect()
            ->route('doctor-fixed-fees.index', $this->filterRedirectParams($request))
            ->with('success', __('configuration.flash.fee_rule_duplicated', ['id' => $copy->id]));
    }

    /**
     * @return array<string, mixed>
     */
    private function filterRedirectParams(Request $request): array
    {
        return $this->mergeConfigurationReturn($request, array_filter([
            'search' => $request->input('return_search'),
            'doctor_id' => $request->input('return_doctor_id'),
            'treatment_id' => $request->input('return_treatment_id'),
            'status' => $request->input('return_status'),
            'currency' => $request->input('return_currency'),
            'page' => $request->input('return_page'),
        ], fn ($value) => $value !== null && $value !== ''));
    }
}
