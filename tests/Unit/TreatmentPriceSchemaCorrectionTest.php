<?php

namespace Tests\Unit;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\NurseCommission;
use App\Models\NurseCommissionRate;
use App\Models\Treatment;
use App\Models\TreatmentPrice;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreatmentPriceSchemaCorrectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_treatment_price_is_nullable(): void
    {
        $clinic = $this->clinic111();

        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'NULL_PRICE',
            'name' => 'Nullable Price Treatment',
        ]);

        $this->assertNull($treatment->treatment_price);
        $this->assertDatabaseHas('treatments', [
            'id' => $treatment->id,
            'treatment_price' => null,
        ]);
    }

    public function test_treatment_price_currency_is_nullable(): void
    {
        $clinic = $this->clinic111();

        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'NULL_CURRENCY',
            'name' => 'Nullable Currency Treatment',
        ]);

        $this->assertNull($treatment->treatment_price_currency);
        $this->assertDatabaseHas('treatments', [
            'id' => $treatment->id,
            'treatment_price_currency' => null,
        ]);
    }

    public function test_treatment_price_is_cast_to_decimal_two_places(): void
    {
        $clinic = $this->clinic111();

        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'DECIMAL_CAST',
            'name' => 'Decimal Cast Treatment',
            'treatment_price' => '199.99',
            'treatment_price_currency' => 'AED',
        ]);

        $treatment->refresh();

        $this->assertSame('199.99', $treatment->treatment_price);
    }

    public function test_treatment_price_currency_is_stored_as_iso_code(): void
    {
        $clinic = $this->clinic111();

        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'USD_PRICE',
            'name' => 'USD Price Treatment',
            'treatment_price' => '100.00',
            'treatment_price_currency' => 'USD',
        ]);

        $this->assertSame('USD', $treatment->treatment_price_currency);
        $this->assertDatabaseHas('treatments', [
            'id' => $treatment->id,
            'treatment_price_currency' => 'USD',
        ]);
    }

    public function test_requires_nurse_commission_defaults_to_false(): void
    {
        $clinic = $this->clinic111();

        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'DEFAULT_FLAG',
            'name' => 'Default Commission Flag',
        ]);

        $this->assertFalse($treatment->requires_nurse_commission);
    }

    public function test_treatment_price_model_is_removed(): void
    {
        $this->assertFalse(class_exists(TreatmentPrice::class));
    }

    public function test_treatment_prices_table_does_not_exist_after_migration(): void
    {
        $this->assertFalse(Schema::hasTable('treatment_prices'));
    }

    public function test_treatment_has_no_treatment_prices_relationship(): void
    {
        $this->assertFalse(method_exists(Treatment::class, 'treatmentPrices'));
    }

    public function test_clinic_has_no_treatment_prices_relationship(): void
    {
        $this->assertFalse(method_exists(Clinic::class, 'treatmentPrices'));
    }

    public function test_nurse_commission_rate_relationships_remain_functional(): void
    {
        $clinic = $this->clinic111();
        $nurse = Nurse::factory()->forClinic($clinic)->create();
        $treatment = Treatment::query()->where('clinic_id', $clinic->id)->firstOrFail();

        $rate = NurseCommissionRate::factory()->create([
            'clinic_id' => $clinic->id,
            'nurse_id' => $nurse->id,
            'treatment_id' => $treatment->id,
        ]);

        $this->assertTrue($nurse->nurseCommissionRates->contains($rate));
        $this->assertTrue($treatment->nurseCommissionRates->contains($rate));
        $this->assertTrue($clinic->nurseCommissionRates->contains($rate));
    }

    public function test_nurse_commission_relationships_remain_functional(): void
    {
        $clinic = $this->clinic111();
        $nurse = Nurse::factory()->forClinic($clinic)->create();
        $treatment = Treatment::query()->where('clinic_id', $clinic->id)->firstOrFail();

        $doctor = Doctor::query()->where('clinic_id', $clinic->id)->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
        ]);
        $workItem = $this->createWorkItem($workRow, ['treatment_id' => $treatment->id]);
        $commission = NurseCommission::factory()->forWorkItem($workItem, $nurse)->create();

        $this->assertTrue($nurse->nurseCommissions->contains($commission));
        $this->assertTrue($treatment->nurseCommissions->contains($commission));
        $this->assertTrue($clinic->nurseCommissions->contains($commission));
    }

    public function test_migrate_fresh_succeeds(): void
    {
        $this->assertTrue(Schema::hasColumn('treatments', 'treatment_price'));
        $this->assertTrue(Schema::hasColumn('treatments', 'treatment_price_currency'));
        $this->assertFalse(Schema::hasTable('treatment_prices'));
    }

    public function test_rollback_recreates_treatment_prices_table_exactly(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);

        $this->assertTrue(Schema::hasTable('treatment_prices'));
        $this->assertFalse(Schema::hasColumn('treatments', 'treatment_price'));
        $this->assertFalse(Schema::hasColumn('treatments', 'treatment_price_currency'));

        $this->assertTrue(Schema::hasColumn('treatment_prices', 'id'));
        $this->assertTrue(Schema::hasColumn('treatment_prices', 'clinic_id'));
        $this->assertTrue(Schema::hasColumn('treatment_prices', 'treatment_id'));
        $this->assertTrue(Schema::hasColumn('treatment_prices', 'unit_price'));
        $this->assertTrue(Schema::hasColumn('treatment_prices', 'currency'));
        $this->assertTrue(Schema::hasColumn('treatment_prices', 'is_active'));
        $this->assertTrue(Schema::hasColumn('treatment_prices', 'created_at'));
        $this->assertTrue(Schema::hasColumn('treatment_prices', 'updated_at'));

        Artisan::call('migrate');
    }

    public function test_migrate_after_rollback_removes_empty_treatment_prices_table_again(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);
        Artisan::call('migrate');

        $this->assertFalse(Schema::hasTable('treatment_prices'));
        $this->assertTrue(Schema::hasColumn('treatments', 'treatment_price'));
        $this->assertTrue(Schema::hasColumn('treatments', 'treatment_price_currency'));
    }

    public function test_data_loss_guard_aborts_when_treatment_prices_contains_rows(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);

        $clinic = $this->clinic111();
        $treatment = Treatment::query()->where('clinic_id', $clinic->id)->firstOrFail();

        DB::table('treatment_prices')->insert([
            'clinic_id' => $clinic->id,
            'treatment_id' => $treatment->id,
            'unit_price' => '200.00',
            'currency' => 'AED',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            Artisan::call('migrate');
            $this->fail('Expected migration to abort when treatment_prices contains rows.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('treatment_prices contains 1 row(s)', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('treatment_prices'));
        $this->assertFalse(Schema::hasColumn('treatments', 'treatment_price'));
        $this->assertFalse(Schema::hasColumn('treatments', 'treatment_price_currency'));
        $this->assertDatabaseCount('treatment_prices', 1);

        DB::table('treatment_prices')->truncate();
        Artisan::call('migrate');

        $this->assertTrue(Schema::hasColumn('treatments', 'treatment_price'));
        $this->assertFalse(Schema::hasTable('treatment_prices'));
    }

    public function test_rollback_down_restores_original_treatment_prices_foreign_keys(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);

        $clinic = $this->clinic111();
        $treatment = Treatment::query()->where('clinic_id', $clinic->id)->firstOrFail();

        DB::table('treatment_prices')->insert([
            'clinic_id' => $clinic->id,
            'treatment_id' => $treatment->id,
            'unit_price' => '250.00',
            'currency' => 'AED',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('treatment_prices', [
            'clinic_id' => $clinic->id,
            'treatment_id' => $treatment->id,
            'unit_price' => '250.00',
            'currency' => 'AED',
            'is_active' => 1,
        ]);

        DB::table('treatment_prices')->truncate();
        Artisan::call('migrate');
    }
}
