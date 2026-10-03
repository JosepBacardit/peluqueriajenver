<?php

namespace App\Booking;

use App\Enums\AppointmentSource;
use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\CustomerCancelledAppointmentMail;
use App\Mail\NewAppointmentMail;
use App\Models\Appointment;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the booking emails synchronously (there is no queue worker in
 * production). A failure is reported to the log and never undoes the
 * booking or the cancellation; creation notices that failed stay pending
 * (null *_notified_at) for `appointments:notify-pending` to retry.
 */
class AppointmentNotifier
{
    public function sendCreationNotices(Appointment $appointment): void
    {
        if ($appointment->customer_email !== null && $appointment->customer_notified_at === null
            && $this->send($appointment->customer_email, new AppointmentConfirmedMail($appointment))) {
            $appointment->forceFill(['customer_notified_at' => now()])->save();
        }

        $salonEmail = $this->salonEmail();

        if ($appointment->source === AppointmentSource::Web && $appointment->salon_notified_at === null && $salonEmail !== null
            && $this->send($salonEmail, new NewAppointmentMail($appointment))) {
            $appointment->forceFill(['salon_notified_at' => now()])->save();
        }
    }

    /**
     * Best effort, without retry: the agenda already shows the
     * cancellation.
     */
    public function sendCancellationNotices(Appointment $appointment, bool $cancelledByCustomer): void
    {
        if ($appointment->customer_email !== null) {
            $this->send($appointment->customer_email, new AppointmentCancelledMail($appointment, $cancelledByCustomer));
        }

        $salonEmail = $this->salonEmail();

        if ($cancelledByCustomer && $salonEmail !== null) {
            $this->send($salonEmail, new CustomerCancelledAppointmentMail($appointment));
        }
    }

    private function salonEmail(): ?string
    {
        $email = config('booking.salon_notification_email');

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function send(string $to, Mailable $mail): bool
    {
        try {
            Mail::to($to)->send($mail);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
