<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinics\ListClinicsRequest;
use App\Http\Requests\Clinics\StoreClinicRequest;
use App\Http\Requests\Clinics\UpdateClinicRequest;
use App\Models\Clinic;
use App\Services\Configuration\ClinicManagementService;
use Illuminate\Database\Eloquent\Builder;
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

        $clinics = $this->filteredClinicsQuery($search, $status)
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('clinics.index', [
            'clinics' => $clinics,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(StoreClinicRequest $request): RedirectResponse
    {
        $clinic = $this->clinicManagementService->create($request->validated());

        return redirect()
            ->route('clinics.index', $this->filterRedirectParams($request))
            ->with('success', "Clinic {$clinic->code} created.");
    }

    public function update(UpdateClinicRequest $request, Clinic $clinic): RedirectResponse
    {
        $this->clinicManagementService->update($clinic, $request->validated());

        return redirect()
            ->route('clinics.index', $this->filterRedirectParams($request))
            ->with('success', "Clinic {$clinic->code} updated.");
    }

    public function destroy(Clinic $clinic): RedirectResponse
    {
        $code = $clinic->code;

        $this->clinicManagementService->deactivate($clinic);

        return redirect()
            ->route('clinics.index')
            ->with('success', "Clinic {$code} deleted.");
    }

    public function activate(Clinic $clinic): RedirectResponse
    {
        $this->clinicManagementService->activate($clinic);

        return redirect()
            ->route('clinics.index')
            ->with('success', "Clinic {$clinic->code} activated.");
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

    /**
     * @return array<string, mixed>
     */
    private function filterRedirectParams(Request $request): array
    {
        return $request->only(['search', 'status', 'page']);
    }
}
