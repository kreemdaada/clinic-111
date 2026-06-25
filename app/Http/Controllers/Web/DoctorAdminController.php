<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctors\UpdateDoctorRequest;
use App\Models\Doctor;
use App\Models\Lab;
use App\Services\DailyReport\DoctorManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

/**
 * Admin-only doctor master data (commission %, deactivate/delete).
 */
class DoctorAdminController extends Controller
{
    public function __construct(
        private readonly DoctorManagementService $doctorManagementService,
    ) {}

    public function index(): View
    {
        $doctors = Doctor::query()
            ->with('defaultLab')
            ->withCount('dailyWorkRows')
            ->orderBy('name')
            ->get();

        return view('doctors.index', [
            'doctors' => $doctors,
            'labs' => Lab::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor): RedirectResponse
    {
        $this->doctorManagementService->update($doctor, $request->validated());

        return redirect()
            ->route('doctors.index')
            ->with('success', "Doctor {$doctor->code} updated.");
    }

    public function destroy(Doctor $doctor): RedirectResponse
    {
        $code = $doctor->code;

        try {
            $result = $this->doctorManagementService->delete($doctor);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['delete' => $exception->getMessage()]);
        }

        $message = $result === 'deleted'
            ? "Doctor {$code} deleted."
            : "Doctor {$code} deactivated (has existing report entries).";

        return redirect()
            ->route('doctors.index')
            ->with('success', $message);
    }
}
