<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Nurse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nurse>
 */
class NurseFactory extends Factory
{
    protected $model = Nurse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::query()->firstOrFail()->id,
            'code' => 'NUR_'.fake()->unique()->numerify('####'),
            'name' => fake()->name(),
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
        return $this->state(fn (array $attributes) => [
            'clinic_id' => $clinic->id,
        ]);
    }
}
