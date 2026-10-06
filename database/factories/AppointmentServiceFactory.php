<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentService>
 */
class AppointmentServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'service_id' => Service::factory(),
            'position' => 1,
            'service_name' => fn (array $attributes) => Service::find($attributes['service_id'])?->name ?? 'Servicio',
            'duration_minutes' => fn (array $attributes) => Service::find($attributes['service_id'])?->duration_minutes ?? 60,
            'price_cents' => null,
        ];
    }
}
