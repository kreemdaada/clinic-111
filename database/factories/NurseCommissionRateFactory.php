<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Nurse;
use App\Models\NurseCommissionRate;
use App\Models\Treatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NurseCommissionRate>
 */
class NurseCommissionRateFactory extends Factory
{
    protected $model = NurseCommissionRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clinic = Clinic::query()->firstOrFail();
        $nurse = Nurse::factory()->forClinic($clinic)->create();
        $treatment = Treatment::query()
            ->where('clinic_id', $clinic->id)
            ->first() ?? Treatment::query()->create([
                'clinic_id' => $clinic->id,
                'code' => 'NCR_'.fake()->unique()->numerify('####'),
                'name' => fake()->words(2, true),
                'has_lab_cost' => false,
                'is_active' => true,
            ]);

        return [
            'clinic_id' => $clinic->id,
            'nurse_id' => $nurse->id,
            'treatment_id' => $treatment->id,
            'commission_percentage' => fake()->randomFloat(2, 5, 25),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(function (array $attributes) use ($clinic) {
            $nurse = Nurse::factory()->forClinic($clinic)->create();
            $treatment = Treatment::query()
                ->where('clinic_id', $clinic->id)
                ->first() ?? Treatment::query()->create([
                    'clinic_id' => $clinic->id,
                    'code' => 'NCR_'.fake()->unique()->numerify('####'),
                    'name' => fake()->words(2, true),
                    'has_lab_cost' => false,
                    'is_active' => true,
                ]);

            return [
                'clinic_id' => $clinic->id,
                'nurse_id' => $nurse->id,
                'treatment_id' => $treatment->id,
            ];
        });
    }
}
