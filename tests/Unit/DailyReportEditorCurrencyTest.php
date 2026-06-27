<?php

namespace Tests\Unit;

use App\Enums\CommissionType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorLabBilling;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Accounting\PaymentCalculationService;
use App\Services\DailyReport\DailyReportEditorService;
use App\Support\MoneyCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportEditorCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_convert_between_usd_and_aed(): void
    {
        $this->assertSame('365.00', MoneyCalculator::convertBetween('100.00', 'USD', 'AED', '3.65'));
        $this->assertSame('100.00', MoneyCalculator::convertBetween('365.00', 'AED', 'USD', '3.65'));
    }

    public function test_convert_between_eur_and_aed(): void
    {
        config(['accounting.currency_to_aed_rates.EUR' => '3.97']);

        $this->assertSame('397.00', MoneyCalculator::convertToAed('100.00', 'EUR'));
        $this->assertSame('100.00', MoneyCalculator::convertFromAed('397.00', 'EUR'));
        $this->assertSame('100.00', MoneyCalculator::convertBetween('397.00', 'AED', 'EUR'));
    }

    public function test_eur_clinic_preview_uses_eur_for_lab_and_payments(): void
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

        $user = new User;
        $user->fill([
            'name' => 'Berlin Admin',
            'email' => 'berlin@test.local',
            'role' => 'admin',
            'clinic_id' => $clinic->id,
        ]);
        $user->password = 'password';
        $user->is_active = true;
        $user->save();

        $lab = Lab::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Main Lab',
            'code' => 'MAIN',
        ]);
        $lab->is_active = true;
        $lab->save();

        $zir = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'ZIR',
            'name' => 'Zircon Crown',
        ]);
        $zir->has_lab_cost = true;
        $zir->is_active = true;
        $zir->save();

        LabPrice::query()->create([
            'clinic_id' => $clinic->id,
            'lab_id' => $lab->id,
            'treatment_id' => $zir->id,
            'unit_cost' => '120.00',
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $doctor = Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr Schmidt',
            'code' => 'SCHMIDT',
            'commission_type' => CommissionType::Percentage,
            'commission_percentage' => 40,
            'default_lab_id' => $lab->id,
            'is_active' => true,
        ]);

        DoctorLabBilling::query()->create([
            'doctor_id' => $doctor->id,
            'treatment_id' => $zir->id,
            'bill_lab_job' => true,
        ]);

        $this->actingAs($user);

        $preview = app(DailyReportEditorService::class)->previewRow(
            $doctor,
            [['code' => 'ZIR', 'quantity' => 1]],
            '500.00',
            '0.00',
            '0.00',
            Carbon::parse('2026-06-15'),
        );

        $this->assertSame('EUR', $preview['currency']);
        $this->assertSame('500.00', $preview['paid_total_aed']);
        $this->assertSame('120.00', $preview['lab_total_aed']);
        $this->assertSame('380.00', $preview['net_total_aed']);
        $this->assertSame('152.00', $preview['doctor_income_aed']);
    }

    public function test_usd_clinic_primary_payments_stay_in_usd(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Syria Clinic',
            'code' => 'SYRIA_PAY',
            'currency' => 'USD',
            'timezone' => 'Asia/Damascus',
            'country' => 'Syria',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $service = app(PaymentCalculationService::class);

        $result = $service->calculateTotalCollected(
            $clinic,
            dhsAmount: '500.00',
            usdAmount: '0.00',
            visaAmount: '100.00',
        );

        $this->assertSame('USD', $result['currency']);
        $this->assertSame('600.00', $result['paid_total']);
        $this->assertSame('2190.00', $result['paid_total_aed']);
    }

    public function test_usd_clinic_preview_lab_only_shows_negative_net_in_usd(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Syria Clinic',
            'code' => 'SYRIA',
            'currency' => 'USD',
            'timezone' => 'Asia/Damascus',
            'country' => 'Syria',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $user = new User;
        $user->fill([
            'name' => 'Syria Admin',
            'email' => 'syria@test.local',
            'role' => 'admin',
            'clinic_id' => $clinic->id,
        ]);
        $user->password = 'password';
        $user->is_active = true;
        $user->save();

        $lab = Lab::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Main Lab',
            'code' => 'MAIN',
        ]);
        $lab->is_active = true;
        $lab->save();

        $zir = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'ZIR',
            'name' => 'Zircon Crown',
        ]);
        $zir->has_lab_cost = true;
        $zir->is_active = true;
        $zir->save();

        $impl = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'IMPL',
            'name' => 'Implant',
        ]);
        $impl->has_lab_cost = true;
        $impl->is_active = true;
        $impl->save();

        $zirPrice = LabPrice::query()->create([
            'clinic_id' => $clinic->id,
            'lab_id' => $lab->id,
            'treatment_id' => $zir->id,
            'unit_cost' => '100.00',
            'currency' => 'USD',
        ]);
        $zirPrice->is_active = true;
        $zirPrice->save();

        $implPrice = LabPrice::query()->create([
            'clinic_id' => $clinic->id,
            'lab_id' => $lab->id,
            'treatment_id' => $impl->id,
            'unit_cost' => '200.00',
            'currency' => 'USD',
        ]);
        $implPrice->is_active = true;
        $implPrice->save();

        $doctor = Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr Jennifer',
            'code' => 'JENNIFER',
            'commission_type' => CommissionType::Percentage,
            'commission_percentage' => 35,
            'default_lab_id' => $lab->id,
            'is_active' => true,
        ]);

        foreach ([$zir, $impl] as $treatment) {
            DoctorLabBilling::query()->create([
                'doctor_id' => $doctor->id,
                'treatment_id' => $treatment->id,
                'bill_lab_job' => true,
            ]);
        }

        $this->actingAs($user);

        $this->assertDatabaseCount('doctor_lab_billings', 2);

        $doctor = $doctor->fresh(['doctorLabBillings']);
        $billingResolver = app(\App\Services\Accounting\LabBillingResolver::class);
        $priceResolver = app(\App\Services\Accounting\LabPriceResolver::class);
        $activeLabs = Lab::query()->where('clinic_id', $clinic->id)->where('is_active', true)->get();

        $this->assertTrue($billingResolver->shouldBillLabJob($doctor, $zir));
        $this->assertTrue($billingResolver->shouldBillLabJob($doctor, $impl));
        $this->assertNotNull($priceResolver->resolveWithLabFallback($doctor, $zir, $activeLabs, Carbon::parse('2026-06-15')));
        $this->assertNotNull($priceResolver->resolveWithLabFallback($doctor, $impl, $activeLabs, Carbon::parse('2026-06-15')));

        $parser = app(\App\Services\Accounting\TreatmentParserService::class);
        $parsed = $parser->parse('ZIR x 1 + IMPL x 1');
        $this->assertCount(2, $parsed, 'Expected parser to recognize ZIR and IMPL for this clinic');

        $preview = app(DailyReportEditorService::class)->previewRow(
            $doctor,
            [
                ['code' => 'ZIR', 'quantity' => 1],
                ['code' => 'IMPL', 'quantity' => 1],
            ],
            '0.00',
            '0.00',
            '0.00',
            Carbon::parse('2026-06-15'),
        );

        $this->assertSame('USD', $preview['currency']);
        $this->assertSame('0.00', $preview['paid_total_aed']);
        $this->assertSame('300.00', $preview['lab_total_aed']);
        $this->assertSame('-300.00', $preview['net_total_aed']);
        $this->assertSame('-105.00', $preview['doctor_income_aed']);
    }
}
