<?php

namespace Database\Factories;

use App\Models\Hairdresser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hairdresser>
 */
class HairdresserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->firstName()),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
