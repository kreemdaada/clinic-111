<?php

namespace App\Http\Controllers\Web;

use App\Enums\ReportSourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DailyReports\StoreDailyWorkRowRequest;
use App\Http\Requests\Doctors\StoreDoctorRequest;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Lab;
use App\Services\DailyReport\DailyReportEditorService;
use App\Services\DailyReport\DoctorManagementService;
use App\Services\DailyReport\DoctorTreatmentCatalogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

/**
 * V2 manual daily report editor (web UI).
 */
class DailyReportEditorController extends Controller
{
    public function __construct(
        private readonly DailyReportEditorService $editorService,
        private readonly DoctorTreatmentCatalogService $treatmentCatalogService,
        private readonly DoctorManagementService $doctorManagementService,
    ) {}

    public function index(): View
    {
        $reports = DailyReport::query()
            ->where('source_type', 'manual_entry')
            ->withCount('dailyWorkRows')
            ->latest('id')
            ->limit(12)
            ->get();

        $doctors = Doctor::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('daily-reports.index', [
            'reports' => $reports,
            'doctors' => $doctors,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->startOfDay();

        if ($dateFrom->format('Y-m') !== $dateTo->format('Y-m')) {
            return back()
                ->withInput()
                ->withErrors(['date_to' => 'Date range must be within the same calendar month.']);
        }

        $monthStart = $dateFrom->copy()->startOfMonth();
        $doctor = Doctor::query()->findOrFail($validated['doctor_id']);

        $label = $validated['label'] ?? sprintf(
            '%s · %s – %s',
            $doctor->code,
            $dateFrom->format('j M'),
            $dateTo->format('j M Y'),
        );

        try {
            $report = $this->editorService->createManualReport($monthStart, $label);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['date_from' => $exception->getMessage()]);
        }

        return redirect()->route('daily-report.edit', [
            'dailyReport' => $report,
            'doctor' => $doctor->id,
            'from' => $dateFrom->toDateString(),
            'to' => $dateTo->toDateString(),
        ]);
    }

    public function destroy(DailyReport $dailyReport): RedirectResponse
    {
        if ($dailyReport->source_type !== ReportSourceType::ManualEntry) {
            abort(404);
        }

        if ($dailyReport->isLocked()) {
            return back()->withErrors([
                'delete' => 'Approved or locked reports cannot be deleted.',
            ]);
        }

        $relativeLogPath = 'import-extractions/report-' . $dailyReport->id . '.json';

        if (Storage::disk('local')->exists($relativeLogPath)) {
            Storage::disk('local')->delete($relativeLogPath);
        }

        $dailyReport->delete();

        return redirect()
            ->route('daily-report.index')
            ->with('status', 'Report deleted.');
    }

    public function edit(DailyReport $dailyReport): View
    {
        $monthStart = Carbon::parse($dailyReport->report_date)->startOfMonth();
        $doctors = Doctor::query()
            ->with('defaultLab')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $rows = $dailyReport->dailyWorkRows()
            ->with(['doctor', 'workItems.treatment', 'workItems.labJob'])
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        return view('daily-reports.editor', [
            'dailyReport' => $dailyReport,
            'monthStart' => $monthStart,
            'daysInMonth' => (int) $monthStart->daysInMonth,
            'doctors' => $doctors,
            'labs' => Lab::query()->where('is_active', true)->orderBy('name')->get(),
            'rows' => $rows,
            'readOnly' => $dailyReport->isLocked(),
        ]);
    }

    public function rows(Request $request, DailyReport $dailyReport): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'day' => ['required', 'integer', 'min:1', 'max:31'],
        ]);

        $day = (int) $validated['day'];
        $monthStart = Carbon::parse($dailyReport->report_date)->startOfMonth();
        $workDate = $monthStart->copy()->day(min($day, $monthStart->daysInMonth));

        $rows = $dailyReport->dailyWorkRows()
            ->with(['workItems.treatment', 'workItems.labJob'])
            ->where('doctor_id', $validated['doctor_id'])
            ->whereDate('work_date', $workDate->toDateString())
            ->orderBy('id')
            ->get()
            ->map(fn (DailyWorkRow $row) => $this->serializeRow($row));

        return response()->json([
            'data' => $rows,
            'day_counts' => $this->editorService->dayCountsForDoctor($dailyReport, (int) $validated['doctor_id']),
        ]);
    }

    public function doctorTreatments(Doctor $doctor, Request $request): JsonResponse
    {
        $month = $request->query('month');
        $workDate = $month
            ? Carbon::createFromFormat('Y-m', (string) $month)->startOfMonth()
            : now()->startOfMonth();

        $day = (int) $request->query('day', 1);
        $workDate = $workDate->copy()->day(min($day, $workDate->daysInMonth));

        return response()->json([
            'data' => $this->treatmentCatalogService->forDoctor($doctor, $workDate),
            'doctor' => [
                'id' => $doctor->id,
                'code' => $doctor->code,
                'name' => $doctor->name,
                'commission_type' => $doctor->commission_type->value,
                'commission_percentage' => $doctor->commission_percentage,
            ],
        ]);
    }

    public function preview(Request $request, DailyReport $dailyReport): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'day' => ['required', 'integer', 'min:1', 'max:31'],
            'dhs_amount' => ['nullable', 'numeric'],
            'cheque_amount' => ['nullable', 'numeric', 'min:0'],
            'tabby_amount' => ['nullable', 'numeric', 'min:0'],
            'usd_amount' => ['nullable', 'numeric', 'min:0'],
            'visa_amount' => ['nullable', 'numeric', 'min:0'],
            'treatment_lines' => ['required', 'array', 'min:1'],
            'treatment_lines.*.code' => ['required', 'string'],
            'treatment_lines.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $doctor = Doctor::query()->findOrFail($validated['doctor_id']);
        $monthStart = Carbon::parse($dailyReport->report_date)->startOfMonth();
        $workDate = $monthStart->copy()->day(min((int) $validated['day'], $monthStart->daysInMonth));

        $preview = $this->editorService->previewRow(
            $doctor,
            $validated['treatment_lines'],
            (string) ($validated['dhs_amount'] ?? '0'),
            (string) ($validated['usd_amount'] ?? '0'),
            (string) ($validated['visa_amount'] ?? '0'),
            $workDate,
            (string) ($validated['cheque_amount'] ?? '0'),
            (string) ($validated['tabby_amount'] ?? '0'),
        );

        return response()->json(['data' => $preview]);
    }

    public function saveRow(StoreDailyWorkRowRequest $request, DailyReport $dailyReport): JsonResponse
    {
        try {
            $workRow = $this->editorService->saveWorkRow($dailyReport, $request->validated());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Row saved.',
            'data' => $this->serializeRow($workRow),
            'day_counts' => $this->editorService->dayCountsForDoctor(
                $dailyReport->fresh(),
                (int) $request->integer('doctor_id'),
            ),
            'report_status' => $dailyReport->fresh()->status->value,
        ]);
    }

    public function deleteRow(DailyReport $dailyReport, DailyWorkRow $dailyWorkRow): JsonResponse
    {
        try {
            $doctorId = $dailyWorkRow->doctor_id;
            $this->editorService->deleteWorkRow($dailyReport, $dailyWorkRow);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Row deleted.',
            'day_counts' => $this->editorService->dayCountsForDoctor($dailyReport->fresh(), (int) $doctorId),
            'report_status' => $dailyReport->fresh()->status->value,
        ]);
    }

    public function storeDoctor(StoreDoctorRequest $request): JsonResponse
    {
        $doctor = $this->doctorManagementService->create($request->validated());

        return response()->json([
            'message' => 'Doctor added.',
            'data' => [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'code' => $doctor->code,
                'commission_type' => $doctor->commission_type->value,
                'commission_percentage' => $doctor->commission_percentage,
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(DailyWorkRow $row): array
    {
        $labTotal = '0.00';

        foreach ($row->workItems as $workItem) {
            if ($workItem->labJob !== null) {
                $labTotal = bcadd($labTotal, (string) $workItem->labJob->total_cost_aed, 2);
            }
        }

        return [
            'id' => $row->id,
            'doctor_id' => $row->doctor_id,
            'work_date' => $row->work_date?->toDateString(),
            'day' => $row->work_date ? $row->work_date->day : null,
            'treatment_text' => $row->treatment_text,
            'treatment_lines' => $row->workItems
                ->map(fn ($workItem) => [
                    'code' => $workItem->treatment->code,
                    'quantity' => (int) $workItem->quantity,
                ])
                ->values()
                ->all(),
            'dhs_amount' => (string) $row->dhs_amount,
            'cheque_amount' => (string) $row->cheque_amount,
            'tabby_amount' => (string) $row->tabby_amount,
            'usd_amount' => (string) $row->usd_amount,
            'visa_amount' => (string) $row->visa_amount,
            'paid_total_aed' => (string) $row->paid_total_aed,
            'lab_total_aed' => $labTotal,
            'source' => is_array($row->raw_data_json) && ($row->raw_data_json['source'] ?? '') === 'manual_v2'
                ? 'manual'
                : 'import',
        ];
    }
}
