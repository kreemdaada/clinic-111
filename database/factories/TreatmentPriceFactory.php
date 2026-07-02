<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Treatment;
use App\Models\TreatmentPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentPrice>
 */
class TreatmentPriceFactory extends Factory
{
    protected $model = TreatmentPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clinic = Clinic::query()->firstOrFail();
        $treatment = Treatment::query()
            ->where('clinic_id', $clinic->id)
            ->first() ?? Treatment::query()->create([
                'clinic_id' => $clinic->id,
                'code' => 'TP_'.fake()->unique()->numerify('####'),
                'name' => fake()->words(2, true),
                'has_lab_cost' => false,
                'is_active' => true,
            ]);

        return [
            'clinic_id' => $clinic->id,
            'treatment_id' => $treatment->id,
            'unit_price' => fake()->randomFloat(2, 50, 500),
            'currency' => 'AED',
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
            $treatment = Treatment::query()
                ->where('clinic_id', $clinic->id)
                ->first() ?? Treatment::query()->create([
                    'clinic_id' => $clinic->id,
                    'code' => 'TP_'.fake()->unique()->numerify('####'),
                    'name' => fake()->words(2, true),
                    'has_lab_cost' => false,
                    'is_active' => true,
                ]);

            return [
                'clinic_id' => $clinic->id,
                'treatment_id' => $treatment->id,
            ];
        });
    }
}
