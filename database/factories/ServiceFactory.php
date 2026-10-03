<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'price_cents' => fake()->optional()->numberBetween(1000, 15000),
            'is_bookable_online' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function notBookableOnline(): static
    {
        return $this->state(fn (array $attributes) => ['is_bookable_online' => false]);
    }
}
