<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Treatments\ListTreatmentsRequest;
use App\Http\Requests\Treatments\StoreTreatmentRequest;
use App\Http\Requests\Treatments\UpdateTreatmentRequest;
use App\Models\Treatment;
use App\Services\Accounting\TreatmentManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only treatment master data.
 */
class TreatmentAdminController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly TreatmentManagementService $treatmentManagementService,
    ) {}

    public function index(ListTreatmentsRequest $request): View
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'all';

        $treatments = $this->filteredTreatmentsQuery($search, $status)
            ->withCount('workItems')
            ->orderBy('code')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('treatments.index', [
            'treatments' => $treatments,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(StoreTreatmentRequest $request): RedirectResponse
    {
        $treatment = $this->treatmentManagementService->create($request->validated());

        return redirect()
            ->route('treatments.index', $this->filterRedirectParams($request))
            ->with('success', "Treatment {$treatment->code} created.");
    }

    public function update(UpdateTreatmentRequest $request, Treatment $treatment): RedirectResponse
    {
        $this->treatmentManagementService->update($treatment, $request->validated());

        return redirect()
            ->route('treatments.index', $this->filterRedirectParams($request))
            ->with('success', "Treatment {$treatment->code} updated.");
    }

    public function destroy(Treatment $treatment): RedirectResponse
    {
        $code = $treatment->code;

        $this->treatmentManagementService->deactivate($treatment);

        return redirect()
            ->route('treatments.index')
            ->with('success', "Treatment {$code} deactivated.");
    }

    public function activate(Treatment $treatment): RedirectResponse
    {
        $this->treatmentManagementService->activate($treatment);

        return redirect()
            ->route('treatments.index')
            ->with('success', "Treatment {$treatment->code} activated.");
    }

    private function filteredTreatmentsQuery(?string $search, string $status): Builder
    {
        $query = Treatment::query();

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('description', 'like', $term);
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
        return array_filter([
            'search' => $request->input('return_search'),
            'status' => $request->input('return_status'),
            'page' => $request->input('return_page'),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
