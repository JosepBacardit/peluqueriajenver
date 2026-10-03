<?php

namespace Database\Factories;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::parse('2030-01-08 10:00');

        return [
            'service_id' => Service::factory(),
            'service_name' => fn (array $attributes) => Service::find($attributes['service_id'])?->name ?? 'Servicio',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'customer_name' => fake()->name(),
            'customer_phone' => '600 123 456',
            'customer_email' => fake()->unique()->safeEmail(),
            'notes' => null,
            'status' => AppointmentStatus::Confirmed,
            'source' => AppointmentSource::Web,
            'token' => Str::random(48),
            'privacy_accepted_at' => now(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
