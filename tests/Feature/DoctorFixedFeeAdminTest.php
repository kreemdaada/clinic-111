<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Accounting\DoctorFixedFeeResolver;
use App\Services\Accounting\MonthlyIncomeCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorFixedFeeAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_admin_can_view_fixed_fee_index_with_modal_markup(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('doctor-fixed-fees.index'))
            ->assertOk()
            ->assertSee('data-open-create', false)
            ->assertSee('dff-create-modal', false)
            ->assertSee('dff-edit-modal', false)
            ->assertSee('dff-edit-btn', false)
            ->assertSee('DOMContentLoaded', false);
    }

    public function test_viewer_cannot_access_fixed_fee_admin(): void
    {
        $viewer = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('doctor-fixed-fees.index'))
            ->assertForbidden();

        $this->actingAsRole('viewer');
        $this->getJson('/api/admin/doctor-fixed-fees')->assertForbidden();
    }

    public function test_accountant_cannot_access_fixed_fee_admin(): void
    {
        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $fee = DoctorFixedFee::query()->firstOrFail();

        $this->actingAs($accountant)
            ->get(route('doctor-fixed-fees.index'))
            ->assertForbidden();

        $this->actingAsRole('accountant');
        $this->postJson('/api/admin/doctor-fixed-fees', [
            'doctor_id' => $fee->doctor_id,
            'treatment_id' => $fee->treatment_id,
            'fee_amount' => 99,
            'currency' => 'AED',
        ])->assertForbidden();
    }

    public function test_admin_can_create_fixed_fee(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $treatment = $this->createTreatment('DFF_NEW', 'DFF New');

        $this->actingAs($admin)
            ->post(route('doctor-fixed-fees.store'), [
                '_form' => 'create',
                'doctor_id' => $doctor->id,
                'treatment_id' => $treatment->id,
                'fee_amount' => '275.00',
                'currency' => 'USD',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('doctor_fixed_fees', [
            'doctor_id' => $doctor->id,
            'treatment_id' => $treatment->id,
            'fee_amount' => '275.00',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DoctorFixedFeeCreated->value,
            'user_id' => $admin->id,
        ]);
    }

    public function test_store_rejects_percentage_doctor(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'CF')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('doctor-fixed-fees.index'))
            ->post(route('doctor-fixed-fees.store'), [
                'doctor_id' => $doctor->id,
                'treatment_id' => $treatment->id,
                'fee_amount' => '100.00',
                'currency' => 'AED',
            ])
            ->assertRedirect(route('doctor-fixed-fees.index'))
            ->assertSessionHasErrors('doctor_id');
    }

    public function test_store_rejects_duplicate_active_fee(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $existing = DoctorFixedFee::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($admin)
            ->from(route('doctor-fixed-fees.index'))
            ->post(route('doctor-fixed-fees.store'), [
                'doctor_id' => $existing->doctor_id,
                'treatment_id' => $existing->treatment_id,
                'fee_amount' => '999.00',
                'currency' => 'AED',
            ])
            ->assertRedirect(route('doctor-fixed-fees.index'))
            ->assertSessionHasErrors('doctor_id');
    }

    public function test_admin_can_deactivate_without_deleting_row(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $fee = $this->createFee('DFF_DEACT');

        $this->actingAs($admin)
            ->delete(route('doctor-fixed-fees.destroy', $fee))
            ->assertRedirect(route('doctor-fixed-fees.index'));

        $this->assertDatabaseHas('doctor_fixed_fees', [
            'id' => $fee->id,
            'is_active' => false,
        ]);
    }

    public function test_duplicate_creates_inactive_copy(): void
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $fee = DoctorFixedFee::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('doctor-fixed-fees.duplicate', $fee))
            ->assertRedirect(route('doctor-fixed-fees.index'));

        $this->assertDatabaseHas('doctor_fixed_fees', [
            'doctor_id' => $fee->doctor_id,
            'treatment_id' => $fee->treatment_id,
            'fee_amount' => (string) $fee->fee_amount,
            'is_active' => false,
        ]);
    }

    public function test_api_lists_fixed_fees(): void
    {
        $this->actingAsRole('admin');

        $response = $this->getJson('/api/admin/doctor-fixed-fees?status=active');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'doctor_code', 'treatment_code', 'fee_amount', 'currency', 'is_active']]]);
    }

    public function test_resolver_uses_active_fee_and_monthly_income_unchanged(): void
    {
        $this->actingAsRole('admin');
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'IMPL')->firstOrFail();
        $resolver = app(DoctorFixedFeeResolver::class);
        $monthlyIncome = app(MonthlyIncomeCalculationService::class);

        $wa = Doctor::query()->where('code', 'WA')->firstOrFail();
        $beforeWa = $monthlyIncome->calculateForMonth('2026-06')
            ->first(fn ($row) => $row->doctorId === $wa->id);

        $resolved = $resolver->resolve($doctor, $treatment);
        $this->assertNotNull($resolved);
        $this->assertSame('500.00', number_format((float) $resolved->fee_amount, 2, '.', ''));

        $afterWa = $monthlyIncome->calculateForMonth('2026-06')
            ->first(fn ($row) => $row->doctorId === $wa->id);

        $this->assertNotNull($beforeWa);
        $this->assertNotNull($afterWa);
        $this->assertSame($beforeWa->doctorIncomeAed, $afterWa->doctorIncomeAed);
    }

    private function createTreatment(string $code, string $name): Treatment
    {
        $treatment = Treatment::query()->create($this->withClinicId([
            'code' => $code,
            'name' => $name,
        ]));
        $treatment->has_lab_cost = false;
        $treatment->is_active = true;
        $treatment->save();

        return $treatment->fresh();
    }

    private function createFee(string $treatmentCode): DoctorFixedFee
    {
        $doctor = Doctor::query()->where('code', 'WA')->firstOrFail();
        $treatment = $this->createTreatment($treatmentCode, $treatmentCode);

        $fee = DoctorFixedFee::query()->create($this->withClinicId([
            'doctor_id' => $doctor->id,
            'treatment_id' => $treatment->id,
            'fee_amount' => '150.00',
            'currency' => 'AED',
            'valid_from' => '2035-01-01',
            'valid_to' => '2035-12-31',
        ]));
        $fee->is_active = true;
        $fee->save();

        return $fee->fresh();
    }
}
