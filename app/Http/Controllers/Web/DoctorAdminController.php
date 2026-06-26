<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctors\UpdateDoctorRequest;
use App\Models\Doctor;
use App\Services\Accounting\LabManagementService;
use App\Services\DailyReport\DoctorManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin-only doctor master data (commission %, deactivate/delete).
 */
class DoctorAdminController extends Controller
{
    public function __construct(
        private readonly DoctorManagementService $doctorManagementService,
        private readonly LabManagementService $labManagementService,
    ) {}

    public function index(): View
    {
        return view('doctors.index', [
            'doctors' => $this->doctorManagementService->listForAdministration(),
            'labs' => $this->labManagementService->listActive(),
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

        $this->doctorManagementService->deactivate($doctor);

        return redirect()
            ->route('doctors.index')
            ->with('success', "Doctor {$code} deleted.");
    }
}
