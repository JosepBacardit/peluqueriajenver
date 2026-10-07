<?php

namespace App\Models;

use Database\Factories\AppointmentServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One service of an appointment, as it was booked: its name, duration and
 * internal price are copied from the service at booking time (PRF-126), so
 * editing the service later never changes the appointment. The price is
 * internal: never shown on a public page or a customer email (PRF-014).
 *
 * @property int $position
 * @property string $service_name
 * @property int $duration_minutes
 * @property int|null $price_cents
 */
#[Fillable(['appointment_id', 'service_id', 'position', 'service_name', 'duration_minutes', 'price_cents'])]
class AppointmentService extends Model
{
    /** @use HasFactory<AppointmentServiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'duration_minutes' => 'integer',
            'price_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The frozen copy of $service at position $position, for a new or
     * changed appointment.
     *
     * @return array{service_id: int, position: int, service_name: string, duration_minutes: int, price_cents: int|null}
     */
    public static function snapshotOf(Service $service, int $position): array
    {
        return [
            'service_id' => $service->id,
            'position' => $position,
            'service_name' => $service->name,
            'duration_minutes' => $service->duration_minutes,
            'price_cents' => $service->price_cents,
        ];
    }
}
