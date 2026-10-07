<?php

namespace App\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property list<array{start: int, minutes: int}>|null $waits measured from starts_at (App\Booking\TimeProfile)
 * @property AppointmentStatus $status
 * @property AppointmentSource $source
 * @property string $services_label
 */
#[Fillable([
    'services_label', 'starts_at', 'ends_at', 'waits',
    'customer_name', 'customer_phone', 'customer_email', 'notes',
    'status', 'source', 'token', 'cancelled_at', 'privacy_accepted_at',
    'customer_notified_at', 'salon_notified_at',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * The most services one appointment can hold, online and in the panel
     * (PRF-125). The single place this limit is written.
     */
    public const MAX_SERVICES = 5;

    /**
     * Separator between the service names in services_label.
     */
    public const SERVICES_LABEL_SEPARATOR = ' + ';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'waits' => 'array',
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
            'cancelled_at' => 'immutable_datetime',
            'privacy_accepted_at' => 'immutable_datetime',
            'customer_notified_at' => 'immutable_datetime',
            'salon_notified_at' => 'immutable_datetime',
        ];
    }

    /**
     * The appointment's services as booked (name, duration and price frozen
     * at booking time, PRF-126), in the order they are done.
     *
     * @return HasMany<AppointmentService, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AppointmentService::class)->orderBy('position');
    }

    /**
     * The current Service rows behind items(), in the same order (their
     * name or duration may have changed since; use items() to show the
     * appointment).
     *
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'appointment_services')
            ->withPivot(['position', 'service_name', 'duration_minutes', 'waits', 'price_cents'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /**
     * Total length of the appointment (the sum of its services'
     * durations), in minutes.
     */
    public function durationMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /**
     * @param  Builder<Appointment>  $query
     */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', AppointmentStatus::Confirmed);
    }

    /**
     * Appointments that share at least a minute with [$from, $to).
     *
     * @param  Builder<Appointment>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function isConfirmed(): bool
    {
        return $this->status === AppointmentStatus::Confirmed;
    }

    /**
     * Spanish long date, e.g. "jueves 10 de enero de 2030".
     */
    public function dayLabel(): string
    {
        return $this->starts_at->locale('es')->translatedFormat('l j \d\e F \d\e Y');
    }

    /**
     * wa.me link to message this customer from the agenda (PRF-095), with
     * the phone normalized to international format: a leading "+" or the
     * "00" international dialing prefix (same meaning, and how part of the
     * clientele still writes it — review finding M1) is stripped, since
     * wa.me wants digits only, no dialing prefix. A number left with no
     * prefix at all (9 digits or fewer, same as PhoneNumber's minimum) is
     * assumed Spanish and gets "34" prepended; anything else is kept as
     * typed, digits only.
     */
    public function customerWhatsappUrl(): string
    {
        $phone = trim($this->customer_phone);
        $hadPrefix = true;

        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        } elseif (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        } else {
            $hadPrefix = false;
        }

        $digits = (string) preg_replace('/\D/', '', $phone);

        if (! $hadPrefix && $digits !== '' && strlen($digits) <= 9) {
            $digits = '34'.$digits;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode("Hola {$this->customer_name}, te escribimos de Peluquería Jenver sobre tu cita.");
    }
}
