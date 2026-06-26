<?php

namespace App\Http\Controllers\Web;

use App\Enums\CommissionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorFixedFees\ListDoctorFixedFeesRequest;
use App\Http\Requests\DoctorFixedFees\StoreDoctorFixedFeeRequest;
use App\Http\Requests\DoctorFixedFees\UpdateDoctorFixedFeeRequest;
use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Treatment;
use App\Services\Accounting\DoctorFixedFeeManagementService;
use Illuminate\Database\Eloquent\Builder;
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
    ) {}

    public function index(ListDoctorFixedFeesRequest $request): View
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $doctorId = isset($validated['doctor_id']) ? (int) $validated['doctor_id'] : null;
        $treatmentId = isset($validated['treatment_id']) ? (int) $validated['treatment_id'] : null;
        $status = $validated['status'] ?? 'all';
        $currency = isset($validated['currency']) ? strtoupper($validated['currency']) : null;

        $fees = $this->filteredFeesQuery($search, $doctorId, $treatmentId, $status, $currency)
            ->with(['doctor', 'treatment'])
            ->orderByDesc('is_active')
            ->orderBy('doctor_id')
            ->orderBy('treatment_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('doctor-fixed-fees.index', [
            'fees' => $fees,
            'doctors' => Doctor::query()->where('commission_type', CommissionType::Fixed)->orderBy('code')->get(),
            'treatments' => Treatment::query()->where('is_active', true)->orderBy('code')->get(),
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

    private function filteredFeesQuery(
        ?string $search,
        ?int $doctorId,
        ?int $treatmentId,
        string $status,
        ?string $currency,
    ): Builder {
        $query = DoctorFixedFee::query();

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->whereHas('doctor', fn (Builder $q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term))
                    ->orWhereHas('treatment', fn (Builder $q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term));
            });
        }

        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }

        if ($treatmentId !== null) {
            $query->where('treatment_id', $treatmentId);
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
