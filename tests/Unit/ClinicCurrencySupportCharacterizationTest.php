<?php

namespace Tests\Unit;

use App\Models\Clinic;
use App\Support\ClinicCurrencySupport;
use Tests\TestCase;

/**
 * Characterization tests for {@see ClinicCurrencySupport}.
 *
 * Expected values are derived with explicit bcmath in the test — never by calling
 * the method under test a second time.
 */
class ClinicCurrencySupportCharacterizationTest extends TestCase
{
    private const USD_TO_AED_RATE = '3.65';

    public function test_aed_clinic_stored_equivalent_is_identity(): void
    {
        $result = ClinicCurrencySupport::toStoredAedEquivalent('250.75', 'AED', self::USD_TO_AED_RATE);

        $this->assertSame('250.75', $result);
    }

    public function test_aed_clinic_display_equivalent_is_identity(): void
    {
        $stored = '250.75';

        $result = ClinicCurrencySupport::fromStoredAedEquivalent($stored, 'AED', self::USD_TO_AED_RATE);

        $this->assertSame($stored, $result);
    }

    public function test_aed_clinic_roundtrip_does_not_double_convert(): void
    {
        $original = '99.99';
        $stored = ClinicCurrencySupport::toStoredAedEquivalent($original, 'AED', self::USD_TO_AED_RATE);
        $display = ClinicCurrencySupport::fromStoredAedEquivalent($stored, 'AED', self::USD_TO_AED_RATE);

        $this->assertSame('99.99', $stored);
        $this->assertSame($original, $display);
    }

    public function test_usd_clinic_converts_original_amount_to_aed_pivot(): void
    {
        $originalUsd = '100.00';
        $expectedAed = bcmul($originalUsd, self::USD_TO_AED_RATE, 2);

        $result = ClinicCurrencySupport::toStoredAedEquivalent($originalUsd, 'USD', self::USD_TO_AED_RATE);

        $this->assertSame('365.00', $expectedAed);
        $this->assertSame($expectedAed, $result);
    }

    public function test_usd_clinic_converts_stored_aed_back_to_usd_display(): void
    {
        $storedAed = '365.00';
        $expectedUsd = bcdiv($storedAed, self::USD_TO_AED_RATE, 2);

        $result = ClinicCurrencySupport::fromStoredAedEquivalent($storedAed, 'USD', self::USD_TO_AED_RATE);

        $this->assertSame('100.00', $expectedUsd);
        $this->assertSame($expectedUsd, $result);
    }

    public function test_usd_clinic_roundtrip_preserves_explicit_example_amount(): void
    {
        $originalUsd = '100.00';
        $expectedAed = bcmul($originalUsd, self::USD_TO_AED_RATE, 2);

        $stored = ClinicCurrencySupport::toStoredAedEquivalent($originalUsd, 'USD', self::USD_TO_AED_RATE);
        $roundtrip = ClinicCurrencySupport::fromStoredAedEquivalent($stored, 'USD', self::USD_TO_AED_RATE);

        $this->assertSame($expectedAed, $stored);
        $this->assertSame($originalUsd, $roundtrip);
    }

    public function test_zero_amount_stores_and_displays_as_zero(): void
    {
        $this->assertSame('0.00', ClinicCurrencySupport::toStoredAedEquivalent('0.00', 'USD', self::USD_TO_AED_RATE));
        $this->assertSame('0.00', ClinicCurrencySupport::fromStoredAedEquivalent('0.00', 'USD', self::USD_TO_AED_RATE));
    }

    public function test_small_decimal_amount_uses_two_scale_bcmath_truncation(): void
    {
        $original = '0.01';
        $expectedAed = bcmul($original, self::USD_TO_AED_RATE, 2);

        $this->assertSame('0.03', $expectedAed);
        $this->assertSame($expectedAed, ClinicCurrencySupport::toStoredAedEquivalent($original, 'USD', self::USD_TO_AED_RATE));
    }

    public function test_amount_with_more_than_two_decimals_truncates_at_two_places_not_half_up(): void
    {
        $original = '10.999';
        $expectedAed = bcmul($original, self::USD_TO_AED_RATE, 2);

        $this->assertSame('40.14', $expectedAed);
        $this->assertSame(
            $expectedAed,
            ClinicCurrencySupport::toStoredAedEquivalent($original, 'USD', self::USD_TO_AED_RATE),
        );
    }

    public function test_string_amount_inputs_are_accepted(): void
    {
        $expectedAed = bcmul('50', self::USD_TO_AED_RATE, 2);

        $this->assertSame($expectedAed, ClinicCurrencySupport::toStoredAedEquivalent('50', 'usd', self::USD_TO_AED_RATE));
    }

    public function test_negative_usd_amount_is_converted_without_rejection(): void
    {
        $original = '-10.00';
        $expectedAed = bcmul($original, self::USD_TO_AED_RATE, 2);

        $this->assertSame('-36.50', $expectedAed);
        $this->assertSame($expectedAed, ClinicCurrencySupport::toStoredAedEquivalent($original, 'USD', self::USD_TO_AED_RATE));
    }

    public function test_foreign_cash_currency_for_aed_clinic_is_usd(): void
    {
        $this->assertSame('USD', ClinicCurrencySupport::foreignCashCurrency('AED'));
        $this->assertSame('USD', ClinicCurrencySupport::foreignCashCurrency('aed'));
    }

    public function test_foreign_cash_currency_for_usd_clinic_is_aed(): void
    {
        $this->assertSame('AED', ClinicCurrencySupport::foreignCashCurrency('USD'));
    }

    public function test_foreign_cash_currency_for_other_clinic_defaults_to_usd(): void
    {
        $this->assertSame('USD', ClinicCurrencySupport::foreignCashCurrency('EUR'));
    }

    public function test_legacy_clinic_111_is_detected_by_configured_code(): void
    {
        $this->seedAccountingData();
        $clinic = $this->clinic111();

        $this->assertTrue(ClinicCurrencySupport::usesLegacyPaymentLayout($clinic));
    }

    public function test_non_legacy_clinic_is_not_treated_as_clinic_111(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Non Legacy',
            'code' => 'NON_LEGACY_CHAR',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'UAE',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $this->assertFalse(ClinicCurrencySupport::usesLegacyPaymentLayout($clinic));
    }

    public function test_to_clinic_currency_is_identity_when_currencies_match(): void
    {
        $this->assertSame(
            '120.50',
            ClinicCurrencySupport::toClinicCurrency('120.50', 'EUR', 'EUR', self::USD_TO_AED_RATE),
        );
    }

    public function test_to_clinic_currency_converts_via_explicit_cross_rate(): void
    {
        config(['accounting.currency_to_aed_rates.EUR' => '4.00']);

        $amountEur = '100.00';
        $amountUsd = '50.00';
        $eurInAed = bcmul($amountEur, '4.00', 2);
        $usdInAed = bcmul($amountUsd, self::USD_TO_AED_RATE, 2);
        $expectedEurFromUsd = bcdiv($usdInAed, '4.00', 2);

        $result = ClinicCurrencySupport::toClinicCurrency($amountUsd, 'USD', 'EUR', self::USD_TO_AED_RATE);

        $this->assertSame('400.00', $eurInAed);
        $this->assertSame('45.62', $expectedEurFromUsd);
        $this->assertSame($expectedEurFromUsd, $result);
    }

    public function test_foreign_cash_in_clinic_currency_returns_zero_for_non_positive_amount(): void
    {
        $this->assertSame('0.00', ClinicCurrencySupport::foreignCashInClinicCurrency('0.00', 'USD', self::USD_TO_AED_RATE));
        $this->assertSame('0.00', ClinicCurrencySupport::foreignCashInClinicCurrency('-5.00', 'USD', self::USD_TO_AED_RATE));
    }

    public function test_foreign_cash_in_clinic_currency_converts_aed_foreign_cash_for_usd_clinic(): void
    {
        $foreignAed = '365.00';
        $expectedUsd = bcdiv($foreignAed, self::USD_TO_AED_RATE, 2);

        $result = ClinicCurrencySupport::foreignCashInClinicCurrency($foreignAed, 'USD', self::USD_TO_AED_RATE);

        $this->assertSame('100.00', $expectedUsd);
        $this->assertSame($expectedUsd, $result);
    }

    public function test_primary_cash_label_is_dhs_for_legacy_clinic_and_iso_code_otherwise(): void
    {
        $this->seedAccountingData();
        $legacy = $this->clinic111();

        $clinic = Clinic::query()->create([
            'name' => 'Euro Label Clinic',
            'code' => 'EUR_LABEL',
            'currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
            'country' => 'Germany',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        $this->assertSame('DHS', ClinicCurrencySupport::primaryCashLabel($legacy));
        $this->assertSame('EUR', ClinicCurrencySupport::primaryCashLabel($clinic));
    }
}
