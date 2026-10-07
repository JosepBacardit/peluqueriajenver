<?php

namespace App\Console\Commands;

use App\Booking\AppointmentNotifier;
use App\Enums\AppointmentSource;
use App\Models\Appointment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Retries the creation notices (customer confirmation, salon notice) that
 * could not be sent when the appointment was booked. Run from cron on the
 * VPS (see AGENTS.md); the 5-minute margin keeps it from racing a booking
 * that is still sending its own emails.
 */
#[Signature('appointments:notify-pending')]
#[Description('Resend booking emails that failed for upcoming confirmed appointments')]
class NotifyPendingAppointmentsCommand extends Command
{
    public function handle(AppointmentNotifier $notifier): int
    {
        $appointments = Appointment::query()
            ->confirmed()
            ->where('starts_at', '>', now())
            ->where('created_at', '<=', now()->subMinutes(5))
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $customer) => $customer->whereNotNull('customer_email')->whereNull('customer_notified_at'))
                    ->orWhere(fn (Builder $salon) => $salon->where('source', AppointmentSource::Web)->whereNull('salon_notified_at'));
            })
            ->orderBy('id')
            ->get();

        foreach ($appointments as $appointment) {
            $notifier->sendCreationNotices($appointment);
        }

        $this->info("Checked {$appointments->count()} appointment(s) with pending notices.");

        return self::SUCCESS;
    }
}
