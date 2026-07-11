<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\NurseCommissionRates\StoreNurseCommissionRateForNurseRequest;
use App\Http\Requests\NurseCommissionRates\UpdateNurseCommissionRateRequest;
use App\Models\Nurse;
use App\Models\NurseCommissionRate;
use App\Services\Accounting\NurseCommissionRateManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class NurseCommissionRateAdminController extends Controller
{
    public function __construct(
        private readonly NurseCommissionRateManagementService $rateManagementService,
    ) {}

    public function storeForNurse(StoreNurseCommissionRateForNurseRequest $request, Nurse $nurse): RedirectResponse
    {
        try {
            $this->rateManagementService->createForNurse($nurse, $request->validated());
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return back()->with('success', __('configuration.flash.nurse_commission_rate_created'));
    }

    public function updateForNurse(UpdateNurseCommissionRateRequest $request, Nurse $nurse, NurseCommissionRate $nurseCommissionRate): RedirectResponse
    {
        $this->assertRateBelongsToNurse($nurse, $nurseCommissionRate);

        try {
            $this->rateManagementService->update($nurseCommissionRate, $request->validated());
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return back()->with('success', __('configuration.flash.nurse_commission_rate_updated'));
    }

    public function destroyForNurse(Nurse $nurse, NurseCommissionRate $nurseCommissionRate): RedirectResponse
    {
        $this->assertRateBelongsToNurse($nurse, $nurseCommissionRate);

        $this->rateManagementService->deactivate($nurseCommissionRate);

        return back()->with('success', __('configuration.flash.nurse_commission_rate_deactivated'));
    }

    public function activateForNurse(Nurse $nurse, NurseCommissionRate $nurseCommissionRate): RedirectResponse
    {
        $this->assertRateBelongsToNurse($nurse, $nurseCommissionRate);

        try {
            $this->rateManagementService->activate($nurseCommissionRate);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return back()->with('success', __('configuration.flash.nurse_commission_rate_activated'));
    }

    private function assertRateBelongsToNurse(Nurse $nurse, NurseCommissionRate $rate): void
    {
        abort_unless((int) $rate->nurse_id === (int) $nurse->id, 404);
    }
}
