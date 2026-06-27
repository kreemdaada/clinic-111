<?php

namespace Tests\Unit;

use App\Models\Clinic;
use App\Services\Import\ExtractionLogPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtractionLogPresentationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_eur_clinic_converts_stored_aed_totals_for_display(): void
    {
        config(['accounting.currency_to_aed_rates.EUR' => '3.97']);

        $clinic = Clinic::query()->create([
            'name' => 'Berlin Clinic',
            'code' => 'BERLIN',
            'currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
            'country' => 'Germany',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $service = app(ExtractionLogPresentationService::class);

        $row = $service->presentImportedRow([
            'paid_total_aed' => '397.00',
            'lab_total_aed' => '119.10',
            'dhs_aed' => '100.00',
            'visa_aed' => '50.00',
            'usd' => '10.00',
        ], $clinic);

        $this->assertSame('100.00', $row['display_paid_total']);
        $this->assertSame('30.00', $row['display_lab_total']);
        $this->assertSame('USD', $row['display_foreign_currency']);
    }

    public function test_clinic_111_keeps_aed_amounts_unchanged(): void
    {
        $this->seedAccountingData();
        $clinic = $this->clinic111();
        $service = app(ExtractionLogPresentationService::class);

        $row = $service->presentImportedRow([
            'paid_total_aed' => '500.00',
            'lab_total_aed' => '120.00',
            'dhs_aed' => '400.00',
            'usd' => '10.00',
            'usd_to_aed' => '36.50',
            'visa_aed' => '63.50',
        ], $clinic);

        $this->assertSame('500.00', $row['display_paid_total']);
        $this->assertSame('36.50', $row['display_foreign_in_clinic']);
    }
}
