<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The salon's booking rules. The table always holds exactly one row,
 * created by its migration.
 */
#[Fillable(['capacity', 'slot_interval_minutes', 'min_notice_minutes', 'max_advance_days', 'cancellation_limit_hours', 'online_booking_enabled'])]
class BookingSetting extends Model
{
    /**
     * Allowed values for the interval between offered start times.
     */
    public const SLOT_INTERVALS = [10, 15, 20, 30, 60];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'slot_interval_minutes' => 'integer',
            'min_notice_minutes' => 'integer',
            'max_advance_days' => 'integer',
            'cancellation_limit_hours' => 'integer',
            'online_booking_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->orderBy('id')->firstOrFail();
    }

    /**
     * The "Reserva online activa" switch in Ajustes: whether the public
     * /reservas page accepts bookings. Not memoized — same read pattern
     * as the rest of this model's call sites — so a change made in the
     * same request (e.g. the admin's own test) is seen immediately.
     */
    public static function onlineBookingEnabled(): bool
    {
        return self::current()->online_booking_enabled;
    }
}
