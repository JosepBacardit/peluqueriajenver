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
    /**
     * @return bool whether the appointment was cancelled by this call
     *              (false if it was already cancelled)
     */
    public function handle(Appointment $appointment): bool
    {
        if (! $appointment->isConfirmed()) {
            return false;
        }

        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return true;
    }
}
