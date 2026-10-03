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
     * The update is conditional on the row still being confirmed, so two
     * simultaneous cancellations (a double click) cancel it, and send the
     * cancellation emails, only once.
     *
     * @return bool whether the appointment was cancelled by this call
     *              (false if it was already cancelled)
     */
    public function handle(Appointment $appointment): bool
    {
        $cancelledAt = now();

        $cancelled = Appointment::query()
            ->whereKey($appointment->getKey())
            ->where('status', AppointmentStatus::Confirmed)
            ->update(['status' => AppointmentStatus::Cancelled, 'cancelled_at' => $cancelledAt]) === 1;

        if ($cancelled) {
            $appointment->forceFill(['status' => AppointmentStatus::Cancelled, 'cancelled_at' => $cancelledAt])->syncOriginal();
        }

        return $cancelled;
    }
}
