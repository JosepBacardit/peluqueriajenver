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
}
