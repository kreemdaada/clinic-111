<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use App\Services\Accounting\PaymentCalculationService;
use App\Support\SecurePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCurrencyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_admin_rejects_unsupported_currency(): void
    {
        $this->seedAccountingData();
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('clinics.store'), [
                'name' => 'Invalid Currency Clinic',
                'code' => 'BAD_CUR',
                'currency' => 'XYZ',
                'timezone' => 'UTC',
                'country' => 'Testland',
            ])
            ->assertSessionHasErrors('currency');

        $this->assertDatabaseMissing('clinics', ['code' => 'BAD_CUR']);
    }

    public function test_clinic_admin_persists_supported_base_currency(): void
    {
        $this->seedAccountingData();
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('clinics.store'), [
                'name' => 'Euro Clinic',
                'code' => 'EURO_M14',
                'currency' => 'eur',
                'timezone' => 'Europe/Berlin',
                'country' => 'Germany',
            ])
            ->assertRedirect(route('clinics.index'));

        $clinic = Clinic::query()->where('code', 'EURO_M14')->firstOrFail();
        $this->assertSame('EUR', $clinic->currency);
        $this->assertSame('Euro', $clinic->baseCurrency()->name);
        $this->assertSame(2, $clinic->currencyMetadata()['precision']);
    }

    public function test_clinic_api_includes_currency_metadata(): void
    {
        $this->seedAccountingData();
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/clinics')
            ->assertOk()
            ->assertJsonPath('data.0.currency', 'AED')
            ->assertJsonPath('data.0.currency_name', 'UAE Dirham')
            ->assertJsonPath('data.0.currency_precision', 2);
    }

    public function test_onboarding_rejects_unsupported_currency(): void
    {
        $this->post(route('register-clinic.store'), [
            'clinic_name' => 'Bad Currency',
            'clinic_code' => 'BADONBOARD',
            'country' => 'Testland',
            'currency' => 'INR',
            'timezone' => 'UTC',
            'owner_name' => 'Owner',
            'owner_email' => 'owner-bad-currency@test.local',
            'owner_password' => SecurePassword::example(),
            'owner_password_confirmation' => SecurePassword::example(),
        ])->assertSessionHasErrors('currency');
    }

    public function test_existing_accounting_calculation_behavior_is_unchanged_for_usd_clinic(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'USD Regression Clinic',
            'code' => 'USD_REG',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'country' => 'USA',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $service = app(PaymentCalculationService::class);

        $result = $service->calculateTotalCollected(
            $clinic,
            dhsAmount: '100.00',
            usdAmount: '0.00',
            visaAmount: '0.00',
        );

        $this->assertSame('100.00', $result['paid_total']);
        $this->assertSame('USD', $result['currency']);
    }
}
