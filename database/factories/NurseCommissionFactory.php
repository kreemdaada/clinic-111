<?php

namespace Database\Factories;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\NurseCommission;
use App\Models\Treatment;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NurseCommission>
 */
class NurseCommissionFactory extends Factory
{
    protected $model = NurseCommission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workItem = $this->createWorkItem();
        $nurse = Nurse::factory()->create(['clinic_id' => $workItem->clinic_id]);
        $treatment = Treatment::query()->findOrFail($workItem->treatment_id);

        return $this->snapshotAttributes($workItem, $nurse, $treatment);
    }

    public function forWorkItem(WorkItem $workItem, ?Nurse $nurse = null): static
    {
        $nurse ??= Nurse::factory()->create(['clinic_id' => $workItem->clinic_id]);
        $treatment = Treatment::query()->findOrFail($workItem->treatment_id);

        return $this->state(fn (array $attributes) => $this->snapshotAttributes($workItem, $nurse, $treatment));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotAttributes(WorkItem $workItem, Nurse $nurse, Treatment $treatment): array
    {
        $unitCommission = '20.00';
        $quantity = max(1, (int) $workItem->quantity);

        return [
            'clinic_id' => $workItem->clinic_id,
            'work_item_id' => $workItem->id,
            'nurse_id' => $nurse->id,
            'nurse_name_snapshot' => $nurse->name,
            'treatment_id' => $treatment->id,
            'treatment_code_snapshot' => $treatment->code,
            'treatment_name_snapshot' => $treatment->name,
            'treatment_price_original' => '200.00',
            'treatment_price_currency' => 'AED',
            'exchange_rate_to_aed' => '1.0000',
            'treatment_price_aed' => '200.00',
            'commission_percentage' => '10.00',
            'unit_commission_aed' => $unitCommission,
            'quantity' => $quantity,
            'total_commission_aed' => bcmul($unitCommission, (string) $quantity, 2),
        ];
    }

    private function createWorkItem(): WorkItem
    {
        $doctor = Doctor::query()->firstOrFail();
        $treatment = Treatment::query()
            ->where('clinic_id', $doctor->clinic_id)
            ->firstOrFail();

        $report = DailyReport::query()->create([
            'clinic_id' => $doctor->clinic_id,
            'report_date' => now()->startOfMonth(),
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);

        $workRow = DailyWorkRow::query()->create([
            'clinic_id' => $doctor->clinic_id,
            'daily_report_id' => $report->id,
            'doctor_id' => $doctor->id,
            'work_date' => now()->toDateString(),
        ]);

        return WorkItem::query()->create([
            'clinic_id' => $doctor->clinic_id,
            'daily_work_row_id' => $workRow->id,
            'treatment_id' => $treatment->id,
            'quantity' => 1,
        ]);
    }
}
