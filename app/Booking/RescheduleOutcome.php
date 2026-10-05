<?php

namespace App\Booking;

use App\Models\Appointment;

/**
 * What RescheduleAppointment changed, decided under its lock from the
 * appointment as it was just before the change, so the caller picks the
 * right email from the real change and not from a copy read earlier.
 */
final readonly class RescheduleOutcome
{
    public function __construct(
        public Appointment $appointment,
        public bool $rescheduled,
        public bool $emailChanged,
    ) {}
}
