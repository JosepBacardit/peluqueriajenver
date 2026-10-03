<?php

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;

/**
 * Cancels a confirmed appointment. Appointments are never deleted: a
 * cancelled one stays in the agenda and stops taking capacity.
 */
class CancelAppointment
{
    public function handle(Appointment $appointment): void
    {
        if (! $appointment->isConfirmed()) {
            return;
        }

        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
