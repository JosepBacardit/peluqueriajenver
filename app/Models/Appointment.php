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
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property AppointmentStatus $status
 * @property AppointmentSource $source
 */
#[Fillable([
    'service_id', 'service_name', 'starts_at', 'ends_at',
    'customer_name', 'customer_phone', 'customer_email', 'notes',
    'status', 'source', 'token', 'cancelled_at', 'privacy_accepted_at',
    'customer_notified_at', 'salon_notified_at',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
            'cancelled_at' => 'immutable_datetime',
            'privacy_accepted_at' => 'immutable_datetime',
            'customer_notified_at' => 'immutable_datetime',
            'salon_notified_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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
     * the phone normalized to international format: a number with no
     * country code of its own (9 digits, same as PhoneNumber's minimum) is
     * assumed Spanish and gets +34; anything else (already starts with "+"
     * or already has more than 9 digits) is kept as typed, digits only.
     */
    public function customerWhatsappUrl(): string
    {
        $digits = (string) preg_replace('/\D/', '', $this->customer_phone);

        if ($digits !== '' && ! str_starts_with(trim($this->customer_phone), '+') && strlen($digits) <= 9) {
            $digits = '34'.$digits;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode("Hola {$this->customer_name}, te escribimos de Peluquería Jenver sobre tu cita.");
    }
}
