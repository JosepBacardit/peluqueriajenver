<?php

namespace App\Booking;

/**
 * Why a start time cannot be booked (see AvailabilityCalculator). The
 * panel tells the salon which one it is when it warns about a move.
 */
enum UnavailabilityReason: string
{
    case InThePast = 'in_the_past';
    case OutsideOpeningHours = 'outside_opening_hours';
    case OutsidePublicRules = 'outside_public_rules';
    case Closed = 'closed';
    case Full = 'full';

    /**
     * Warning shown in the admin panel when moving an appointment.
     */
    public function adminMessage(): string
    {
        return match ($this) {
            self::InThePast => 'Esa hora ya ha pasado.',
            self::OutsideOpeningHours => 'Esa hora cae fuera del horario de apertura: el salón está cerrado ese día o a esa hora, o la cita terminaría después del cierre.',
            self::OutsidePublicRules => 'Esa hora no se puede reservar desde la web.',
            self::Closed => 'Esa hora coincide con un cierre puntual de la agenda (apartado «Cierres»).',
            self::Full => 'Esa hora ya no tiene plaza libre: las citas confirmadas ocupan toda la capacidad del salón.',
        };
    }
}
