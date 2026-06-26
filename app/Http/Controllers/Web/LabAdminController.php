<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Labs\ListLabsRequest;
use App\Http\Requests\Labs\StoreLabRequest;
use App\Http\Requests\Labs\UpdateLabRequest;
use App\Models\Lab;
use App\Services\Accounting\LabManagementService;
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

        $labs = $this->labManagementService->listQuery($search, $status)
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
}
