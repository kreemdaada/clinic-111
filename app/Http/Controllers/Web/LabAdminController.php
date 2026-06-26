<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Labs\ListLabsRequest;
use App\Http\Requests\Labs\StoreLabRequest;
use App\Http\Requests\Labs\UpdateLabRequest;
use App\Models\Lab;
use App\Services\Accounting\LabManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin-only laboratory master data.
 */
class LabAdminController extends Controller
{
    public function __construct(
        private readonly LabManagementService $labManagementService,
    ) {}

    public function index(ListLabsRequest $request): View
    {
        $validated = $request->validated();
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'all';

        $labs = $this->filteredLabsQuery($search, $status)
            ->withCount(['labJobs', 'labPrices'])
            ->orderBy('name')
            ->get();

        return view('labs.index', [
            'labs' => $labs,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(StoreLabRequest $request): RedirectResponse
    {
        $lab = $this->labManagementService->create($request->validated());

        return redirect()
            ->route('labs.index')
            ->with('success', "Laboratory {$lab->code} created.");
    }

    public function update(UpdateLabRequest $request, Lab $lab): RedirectResponse
    {
        $this->labManagementService->update($lab, $request->validated());

        return redirect()
            ->route('labs.index', $request->only(['search', 'status']))
            ->with('success', "Laboratory {$lab->code} updated.");
    }

    public function destroy(Lab $lab): RedirectResponse
    {
        $code = $lab->code;

        $this->labManagementService->deactivate($lab);

        return redirect()
            ->route('labs.index')
            ->with('success', "Laboratory {$code} deleted.");
    }

    public function activate(Lab $lab): RedirectResponse
    {
        $this->labManagementService->activate($lab);

        return redirect()
            ->route('labs.index')
            ->with('success', "Laboratory {$lab->code} activated.");
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
