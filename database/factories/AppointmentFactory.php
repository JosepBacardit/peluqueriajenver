<?php

namespace Database\Factories;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Every appointment it creates has at least one service: by default a new
 * Service named like services_label and as long as the appointment, or the
 * given ones with withServices().
 *
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Appointment $appointment) {
            $services = $appointment->relationLoaded('servicesToBook')
                ? $appointment->getRelation('servicesToBook')
                : collect([Service::factory()->create([
                    'name' => mb_substr($appointment->services_label, 0, 100),
                    'duration_minutes' => max(5, min(600, $appointment->durationMinutes())),
                ])]);
            $appointment->unsetRelation('servicesToBook');

            foreach ($services->values() as $index => $service) {
                $appointment->items()->create(AppointmentService::snapshotOf($service, $index + 1));
            }
        });
    }

    /**
     * Books these services, in this order (their names joined as
     * services_label). Unless ends_at is given, the appointment lasts the
     * sum of their durations.
     */
    public function withServices(Service ...$services): static
    {
        return $this
            ->state(fn (array $attributes) => [
                'services_label' => collect($services)->pluck('name')->implode(Appointment::SERVICES_LABEL_SEPARATOR),
                'ends_at' => fn (array $attributes) => CarbonImmutable::parse($attributes['starts_at'])->addMinutes(collect($services)->sum('duration_minutes')),
            ])
            ->afterMaking(fn (Appointment $appointment) => $appointment->setRelation('servicesToBook', collect($services)));
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::parse('2030-01-08 10:00');

        return [
            'services_label' => fn () => ucfirst(fake()->words(2, true)),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->unique()->numerify('6## ### ###'),
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
