<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\LabPrice;
use App\Models\NurseCommission;
use App\Models\NurseCommissionRate;
use App\Models\Treatment;
use App\Models\User;
use App\Support\ConfigurationReturnContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentPriceFieldsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_treatment_without_price_when_commission_not_required(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'NO_PRICE',
                'name' => 'No Price Treatment',
            ]))
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHas('success');

        $treatment = Treatment::query()->where('code', 'NO_PRICE')->firstOrFail();

        $this->assertNull($treatment->treatment_price);
        $this->assertNull($treatment->treatment_price_currency);
        $this->assertFalse($treatment->requires_nurse_commission);
    }

    public function test_treatment_with_price_and_currency(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'PRICED',
                'name' => 'Priced Treatment',
                'treatment_price' => '250.50',
                'treatment_price_currency' => 'aed',
            ]))
            ->assertRedirect(route('treatments.index'));

        $treatment = Treatment::query()->where('code', 'PRICED')->firstOrFail();

        $this->assertSame('250.50', $treatment->treatment_price);
        $this->assertSame('AED', $treatment->treatment_price_currency);
    }

    public function test_treatment_price_is_stored_as_decimal_two_places(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'DECIMAL',
                'name' => 'Decimal Treatment',
                'treatment_price' => '199.99',
                'treatment_price_currency' => 'AED',
            ]))
            ->assertRedirect(route('treatments.index'));

        $treatment = Treatment::query()->where('code', 'DECIMAL')->firstOrFail();

        $this->assertSame('199.99', $treatment->fresh()->treatment_price);
    }

    public function test_currency_is_uppercase_normalized(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'USD_TX',
                'name' => 'USD Treatment',
                'treatment_price' => '100.00',
                'treatment_price_currency' => 'usd',
            ]))
            ->assertRedirect(route('treatments.index'));

        $this->assertSame('USD', Treatment::query()->where('code', 'USD_TX')->value('treatment_price_currency'));
    }

    public function test_price_without_currency_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('treatments.index'))
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'PRICE_ONLY',
                'name' => 'Price Only',
                'treatment_price' => '100.00',
            ]))
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHasErrors('treatment_price_currency');
    }

    public function test_currency_without_price_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('treatments.index'))
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'CURR_ONLY',
                'name' => 'Currency Only',
                'treatment_price_currency' => 'AED',
            ]))
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHasErrors('treatment_price');
    }

    public function test_zero_price_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('treatments.index'))
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'ZERO',
                'name' => 'Zero Price',
                'treatment_price' => '0',
                'treatment_price_currency' => 'AED',
            ]))
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHasErrors('treatment_price');
    }

    public function test_negative_price_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('treatments.index'))
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'NEG',
                'name' => 'Negative Price',
                'treatment_price' => '-10.00',
                'treatment_price_currency' => 'AED',
            ]))
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHasErrors('treatment_price');
    }

    public function test_more_than_two_decimal_places_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('treatments.index'))
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'PREC',
                'name' => 'Precision',
                'treatment_price' => '10.999',
                'treatment_price_currency' => 'AED',
            ]))
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHasErrors('treatment_price');
    }

    public function test_unknown_currency_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('treatments.index'))
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'BAD_CUR',
                'name' => 'Bad Currency',
                'treatment_price' => '100.00',
                'treatment_price_currency' => 'XYZ',
            ]))
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHasErrors('treatment_price_currency');
    }

    public function test_commission_required_without_price_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('treatments.index'))
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'COMM_NO_PRICE',
                'name' => 'Commission No Price',
                'requires_nurse_commission' => '1',
            ]))
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHasErrors(['treatment_price', 'treatment_price_currency']);
    }

    public function test_commission_required_without_currency_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('treatments.index'))
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'COMM_NO_CUR',
                'name' => 'Commission No Currency',
                'requires_nurse_commission' => '1',
                'treatment_price' => '200.00',
            ]))
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHasErrors('treatment_price_currency');
    }

    public function test_commission_required_treatment_with_valid_price_is_created(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'COMM_OK',
                'name' => 'Commission OK',
                'requires_nurse_commission' => '1',
                'treatment_price' => '200.00',
                'treatment_price_currency' => 'AED',
            ]))
            ->assertRedirect(route('treatments.index'));

        $treatment = Treatment::query()->where('code', 'COMM_OK')->firstOrFail();

        $this->assertTrue($treatment->requires_nurse_commission);
        $this->assertSame('200.00', $treatment->treatment_price);
        $this->assertSame('AED', $treatment->treatment_price_currency);
    }

    public function test_update_to_commission_required_with_valid_price(): void
    {
        $admin = $this->admin();
        $treatment = $this->createTreatment('UPD_COMM', 'Update Commission');

        $this->actingAs($admin)
            ->put(route('treatments.update', $treatment), $this->validPayload([
                'code' => 'UPD_COMM',
                'name' => 'Update Commission',
                'requires_nurse_commission' => '1',
                'treatment_price' => '360.00',
                'treatment_price_currency' => 'AED',
                'is_active' => '1',
            ]))
            ->assertRedirect(route('treatments.index'));

        $treatment->refresh();

        $this->assertTrue($treatment->requires_nurse_commission);
        $this->assertSame('360.00', $treatment->treatment_price);
    }

    public function test_update_to_commission_required_without_price_is_rejected(): void
    {
        $admin = $this->admin();
        $treatment = $this->createTreatment('UPD_FAIL', 'Update Fail');

        $this->actingAs($admin)
            ->from(route('treatments.index'))
            ->put(route('treatments.update', $treatment), $this->validPayload([
                'code' => 'UPD_FAIL',
                'name' => 'Update Fail',
                'requires_nurse_commission' => '1',
                'is_active' => '1',
            ]))
            ->assertRedirect(route('treatments.index'))
            ->assertSessionHasErrors('treatment_price');
    }

    public function test_disabling_commission_checkbox_keeps_price_and_currency(): void
    {
        $admin = $this->admin();
        $treatment = Treatment::query()->create($this->withClinicId([
            'code' => 'KEEP_PRICE',
            'name' => 'Keep Price',
            'treatment_price' => '150.00',
            'treatment_price_currency' => 'AED',
        ]));
        $treatment->requires_nurse_commission = true;
        $treatment->is_active = true;
        $treatment->save();

        $this->actingAs($admin)
            ->put(route('treatments.update', $treatment), $this->validPayload([
                'code' => 'KEEP_PRICE',
                'name' => 'Keep Price',
                'requires_nurse_commission' => '0',
                'treatment_price' => '150.00',
                'treatment_price_currency' => 'AED',
                'is_active' => '1',
            ]))
            ->assertRedirect(route('treatments.index'));

        $treatment->refresh();

        $this->assertFalse($treatment->requires_nurse_commission);
        $this->assertSame('150.00', $treatment->treatment_price);
        $this->assertSame('AED', $treatment->treatment_price_currency);
    }

    public function test_nurse_commission_required_clears_external_lab_cost(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'LAB_AND_PRICE',
                'name' => 'Lab And Price',
                'has_lab_cost' => '1',
                'requires_nurse_commission' => '1',
                'treatment_price' => '200.00',
                'treatment_price_currency' => 'AED',
            ]))
            ->assertRedirect(route('treatments.index'));

        $treatment = Treatment::query()->where('code', 'LAB_AND_PRICE')->firstOrFail();

        $this->assertFalse($treatment->has_lab_cost);
        $this->assertTrue($treatment->requires_nurse_commission);
        $this->assertSame('200.00', $treatment->treatment_price);
    }

    public function test_index_shows_price_and_commission_status(): void
    {
        $admin = $this->admin();

        $treatment = Treatment::query()->create($this->withClinicId([
            'code' => 'LIST_PRICE',
            'name' => 'List Price Treatment',
            'treatment_price' => '200.00',
            'treatment_price_currency' => 'AED',
        ]));
        $treatment->requires_nurse_commission = true;
        $treatment->is_active = true;
        $treatment->save();

        $this->actingAs($admin)
            ->get(route('treatments.index', ['search' => 'LIST_PRICE']))
            ->assertOk()
            ->assertSee('200.00 AED', false)
            ->assertSee('Required', false);
    }

    public function test_api_returns_new_fields(): void
    {
        $this->actingAsRole('admin');

        $response = $this->postJson('/api/admin/treatments', [
            'code' => 'API_PRICE',
            'name' => 'API Price Treatment',
            'requires_nurse_commission' => true,
            'treatment_price' => '180.00',
            'treatment_price_currency' => 'EUR',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.treatment_price', '180.00')
            ->assertJsonPath('data.treatment_price_currency', 'EUR')
            ->assertJsonPath('data.requires_nurse_commission', true);
    }

    public function test_audit_snapshot_contains_new_fields(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'AUDIT_PRICE',
                'name' => 'Audit Price Treatment',
                'requires_nurse_commission' => '1',
                'treatment_price' => '200.00',
                'treatment_price_currency' => 'AED',
            ]))
            ->assertRedirect(route('treatments.index'));

        $treatment = Treatment::query()->where('code', 'AUDIT_PRICE')->firstOrFail();

        $log = AuditLog::query()
            ->where('action', AuditAction::TreatmentCreated->value)
            ->where('auditable_id', $treatment->id)
            ->firstOrFail();

        $this->assertSame('200.00', $log->new_values['treatment_price']);
        $this->assertSame('AED', $log->new_values['treatment_price_currency']);
        $this->assertTrue($log->new_values['requires_nurse_commission']);
    }

    public function test_cross_clinic_update_remains_blocked(): void
    {
        $admin = $this->admin();
        $tenant222 = $this->seedClinic222Tenant();

        $this->actingAs($admin)
            ->put(route('treatments.update', $tenant222['treatment']), $this->validPayload([
                'code' => $tenant222['treatment']->code,
                'name' => 'Cross Clinic Hack',
                'treatment_price' => '999.00',
                'treatment_price_currency' => 'AED',
                'is_active' => '1',
            ]))
            ->assertNotFound();
    }

    public function test_search_status_pagination_and_back_navigation_still_work(): void
    {
        $admin = $this->admin();

        $this->createTreatment('NAV_TX', 'Navigation Treatment');

        $this->actingAs($admin)
            ->get(route('treatments.index', [
                'from' => ConfigurationReturnContext::VALUE,
                'search' => 'NAV_TX',
                'status' => 'active',
                'page' => 1,
            ]))
            ->assertOk()
            ->assertSee(__('navigation.back_to_configuration'), false)
            ->assertSee('NAV_TX', false);
    }

    public function test_no_nurse_commissions_are_created(): void
    {
        $before = NurseCommission::query()->count();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'NO_COMM_ROW',
                'name' => 'No Commission Row',
                'requires_nurse_commission' => '1',
                'treatment_price' => '200.00',
                'treatment_price_currency' => 'AED',
            ]))
            ->assertRedirect(route('treatments.index'));

        $this->assertSame($before, NurseCommission::query()->count());
    }

    public function test_nurse_commission_rates_are_not_modified(): void
    {
        $before = NurseCommissionRate::query()->count();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'NO_RATE',
                'name' => 'No Rate Change',
                'requires_nurse_commission' => '1',
                'treatment_price' => '200.00',
                'treatment_price_currency' => 'AED',
            ]))
            ->assertRedirect(route('treatments.index'));

        $this->assertSame($before, NurseCommissionRate::query()->count());
    }

    public function test_lab_prices_are_not_modified(): void
    {
        $before = LabPrice::query()->count();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('treatments.store'), $this->validPayload([
                'code' => 'NO_LAB_PRICE',
                'name' => 'No Lab Price Change',
                'has_lab_cost' => '1',
                'treatment_price' => '200.00',
                'treatment_price_currency' => 'AED',
            ]))
            ->assertRedirect(route('treatments.index'));

        $this->assertSame($before, LabPrice::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'TEST',
            'name' => 'Test Treatment',
            'description' => null,
            'has_lab_cost' => '0',
        ], $overrides);
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@clinic.test')->firstOrFail();
    }

    private function createTreatment(string $code, string $name): Treatment
    {
        $treatment = Treatment::query()->create($this->withClinicId([
            'code' => $code,
            'name' => $name,
        ]));
        $treatment->is_active = true;
        $treatment->save();

        return $treatment->fresh();
    }
}
