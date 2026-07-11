<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinics\ListClinicsRequest;
use App\Http\Requests\Clinics\StoreClinicRequest;
use App\Http\Requests\Clinics\UpdateClinicRequest;
use App\Models\Clinic;
use App\Services\Configuration\ClinicManagementService;
use App\Support\ClinicRegistrationOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only clinic tenant master data.
 */
class ClinicAdminController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly ClinicManagementService $clinicManagementService,
    ) {}

    public function index(ListClinicsRequest $request): View
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'all';

        $clinics = $this->clinicManagementService->listQuery($search, $status)
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('clinics.index', [
            'clinics' => $clinics,
            'search' => $search,
            'status' => $status,
            'currencies' => ClinicRegistrationOptions::currencies(),
        ]);
    }

    public function store(StoreClinicRequest $request): RedirectResponse
    {
        $clinic = $this->clinicManagementService->create($request->validated());

        return redirect()
            ->route('clinics.index', $this->filterRedirectParams($request))
            ->with('success', __('configuration.flash.clinic_created', ['code' => $clinic->code]));
    }

    public function update(UpdateClinicRequest $request, Clinic $clinic): RedirectResponse
    {
        $this->clinicManagementService->update($clinic, $request->validated());

        return redirect()
            ->route('clinics.index', $this->filterRedirectParams($request))
            ->with('success', __('configuration.flash.clinic_updated', ['code' => $clinic->code]));
    }

    public function destroy(Clinic $clinic): RedirectResponse
    {
        $code = $clinic->code;

        $this->clinicManagementService->deactivate($clinic);

        return redirect()
            ->route('clinics.index')
            ->with('success', __('configuration.flash.clinic_deleted', ['code' => $code]));
    }

    public function activate(Clinic $clinic): RedirectResponse
    {
        $this->clinicManagementService->activate($clinic);

        return redirect()
            ->route('clinics.index')
            ->with('success', __('configuration.flash.clinic_activated', ['code' => $clinic->code]));
    }

    /**
     * @return array<string, mixed>
     */
    private function filterRedirectParams(Request $request): array
    {
        return $request->only(['search', 'status', 'page']);
    }
}
