<?php

namespace App\View\Composers;

use App\Exceptions\CurrentClinicException;
use App\Services\Configuration\CurrentClinicResolver;
use App\Support\ClinicCurrencySupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Shares the active clinic currency context with authenticated web views.
 */
class ClinicContextComposer
{
    public function __construct(
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    public function compose(View $view): void
    {
        if (! Auth::check()) {
            return;
        }

        try {
            $clinic = $this->currentClinicResolver->resolve();
        } catch (CurrentClinicException) {
            return;
        }

        $clinicCurrency = ClinicCurrencySupport::baseCurrency($clinic);

        $view->with([
            'currentClinic' => $clinic,
            'clinicCurrency' => $clinicCurrency,
            'foreignCashCurrency' => ClinicCurrencySupport::foreignCashCurrency($clinicCurrency),
            'primaryCashLabel' => ClinicCurrencySupport::primaryCashLabel($clinic),
            'usesLegacyPaymentLayout' => ClinicCurrencySupport::usesLegacyPaymentLayout($clinic),
        ]);
    }
}
