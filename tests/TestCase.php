<?php

namespace Tests;

use App\Models\Clinic;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\LabJob;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WorkItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function seedAccountingData(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    protected function actingAsRole(string $role): User
    {
        $email = match ($role) {
            'admin' => 'admin@clinic.test',
            'accountant' => 'accountant@clinic.test',
            default => 'viewer@clinic.test',
        };

        $user = User::query()->where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function clinic111(): Clinic
    {
        return Clinic::query()->where('code', 'CLINIC_111')->firstOrFail();
    }

    protected function clinic222(): Clinic
    {
        return Clinic::query()->where('code', 'CLINIC_222')->firstOrFail();
    }

    /**
     * @return array{clinic: Clinic, admin: User}
     */
    protected function seedClinic222Tenant(): array
    {
        $clinic = Clinic::query()->create([
            'name' => 'Clinic 222',
            'code' => 'CLINIC_222',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'United Arab Emirates',
        ]);

        $admin = new User;
        $admin->fill([
            'name' => 'Clinic 222 Admin',
            'email' => 'admin@clinic222.test',
            'role' => 'admin',
            'clinic_id' => $clinic->id,
        ]);
        $admin->password = 'password';
        $admin->is_active = true;
        $admin->email_verified_at = now();
        $admin->save();

        $lab = Lab::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Clinic 222 Lab',
            'code' => 'C222_LAB',
        ]);

        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => 'C222_TX',
            'name' => 'Clinic 222 Treatment',
        ]);
        $treatment->has_lab_cost = true;
        $treatment->is_active = true;
        $treatment->save();

        $doctor = Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr Clinic 222',
            'code' => 'C222_DOC',
            'commission_type' => 'percentage',
            'commission_percentage' => 25,
            'default_lab_id' => $lab->id,
            'is_active' => true,
        ]);

        $price = LabPrice::query()->create([
            'clinic_id' => $clinic->id,
            'lab_id' => $lab->id,
            'treatment_id' => $treatment->id,
            'unit_cost' => '100.00',
            'currency' => 'AED',
        ]);
        $price->is_active = true;
        $price->save();

        $viewer = new User;
        $viewer->fill([
            'name' => 'Clinic 222 Viewer',
            'email' => 'viewer@clinic222.test',
            'role' => 'viewer',
            'clinic_id' => $clinic->id,
        ]);
        $viewer->password = 'password';
        $viewer->is_active = true;
        $viewer->email_verified_at = now();
        $viewer->save();

        return compact('clinic', 'admin', 'lab', 'treatment', 'doctor', 'price', 'viewer');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function withClinicId(array $attributes = []): array
    {
        return array_merge(['clinic_id' => $this->clinic111()->id], $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createDailyReport(array $attributes = []): DailyReport
    {
        return DailyReport::query()->create($this->withClinicId($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function withClinic222Id(array $attributes = []): array
    {
        return array_merge(['clinic_id' => $this->clinic222()->id], $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createClinic222DailyReport(array $attributes = []): DailyReport
    {
        return DailyReport::query()->create($this->withClinic222Id($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createDailyWorkRow(DailyReport $dailyReport, array $attributes = []): DailyWorkRow
    {
        return DailyWorkRow::query()->create(array_merge([
            'clinic_id' => $dailyReport->clinic_id,
            'daily_report_id' => $dailyReport->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createWorkItem(DailyWorkRow $dailyWorkRow, array $attributes = []): WorkItem
    {
        return WorkItem::query()->create(array_merge([
            'clinic_id' => $dailyWorkRow->clinic_id,
            'daily_work_row_id' => $dailyWorkRow->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createLabJob(WorkItem $workItem, array $attributes = []): LabJob
    {
        return LabJob::query()->create(array_merge([
            'clinic_id' => $workItem->clinic_id,
            'work_item_id' => $workItem->id,
        ], $attributes));
    }

    protected function authenticateAdmin(): User
    {
        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $this->actingAs($admin);

        return $admin;
    }

    protected function verifyUser(User $user): User
    {
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user->fresh();
    }
}
