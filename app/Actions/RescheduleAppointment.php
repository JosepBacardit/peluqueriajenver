<?php

namespace App\Actions;

use App\Booking\AppointmentNotMovableException;
use App\Booking\AvailabilityCalculator;
use App\Booking\SlotUnavailableException;
use App\Booking\StartTimeInPastException;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Moves a confirmed, upcoming appointment to another time and/or service
 * from the admin panel, and updates the customer's details with it. The
 * same row is updated: its id and its token (the customer's personal
 * link) never change, and nothing is written about the change itself.
 */
class RescheduleAppointment
{
    public function __construct(private AvailabilityCalculator $calculator) {}

    /**
     * Same concurrency scheme as CreateAppointment::handle(), plus the
     * conditional update of CancelAppointment:
     *
     * The transaction first takes the row lock on the single
     * booking_settings row, which every booking and every move takes too,
     * so moves and bookings are serialised and cannot both take the last
     * place. The locking read MUST be the first query of the transaction:
     * under MySQL/InnoDB REPEATABLE READ the snapshot used by the later
     * plain reads (the appointment's current state, availability) is
     * taken at the first non-locking read, so only reading after the lock
     * lets a request that waited for it see what the previous one just
     * committed (a concurrent move of this same appointment, or a booking
     * that took the new time). A test asserts the lock comes first.
     *
     * Cancellations do not take that lock, so the final update is
     * conditional on the row still being confirmed: a cancellation that
     * commits in between makes it match no row (UPDATE always reads the
     * latest committed version), and the move is refused instead of
     * reviving or half-changing a cancelled appointment. All the new
     * values are written by that single UPDATE, so two concurrent moves
     * leave the whole result of the one that ran last, never a mix.
     *
     * The appointment itself never counts against its new time
     * (excludeAppointmentId), so it can be moved into the time it holds
     * now. $ignoreHoursAndCapacity is the salon's explicit "save anyway"
     * after being warned that the new time is full or outside opening
     * hours; a start time in the past is refused even then.
     *
     * When the service does not change, the appointment keeps the name and
     * duration it was booked with (as when the service is edited later);
     * a different service brings its own current name and duration.
     *
     * @param  array{customer_name: string, customer_phone: string, customer_email: string|null, notes: string|null}  $customer
     *
     * @throws AppointmentNotMovableException
     * @throws StartTimeInPastException
     * @throws SlotUnavailableException
     */
    public function handle(
        Appointment $appointment,
        Service $service,
        CarbonImmutable $startsAt,
        array $customer,
        bool $ignoreHoursAndCapacity,
        ?CarbonImmutable $now = null,
    ): Appointment {
        $now ??= CarbonImmutable::now();

        return DB::transaction(function () use ($appointment, $service, $startsAt, $customer, $ignoreHoursAndCapacity, $now): Appointment {
            BookingSetting::query()->lockForUpdate()->orderBy('id')->firstOrFail();

            $current = Appointment::query()->find($appointment->getKey());

            if ($current === null || ! $current->isConfirmed() || $current->starts_at->lte($now)) {
                throw new AppointmentNotMovableException;
            }

            if ($startsAt->lt($now)) {
                throw new StartTimeInPastException;
            }

            $keepsService = (int) $current->service_id === (int) $service->id;
            $durationMinutes = $keepsService
                ? (int) $current->starts_at->diffInMinutes($current->ends_at)
                : $service->duration_minutes;

            if (! $ignoreHoursAndCapacity
                && ! $this->calculator->isAvailable($durationMinutes, $startsAt, $now, applyPublicRules: false, excludeAppointmentId: $current->id)) {
                throw new SlotUnavailableException;
            }

            $attributes = [
                'service_id' => $service->id,
                'service_name' => $keepsService ? $current->service_name : $service->name,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($durationMinutes),
                'customer_name' => trim($customer['customer_name']),
                'customer_phone' => trim($customer['customer_phone']),
                'customer_email' => $customer['customer_email'] === null ? null : Str::lower(trim($customer['customer_email'])),
                'notes' => $customer['notes'] === null ? null : trim($customer['notes']),
            ];

            $moved = Appointment::query()
                ->whereKey($current->getKey())
                ->where('status', AppointmentStatus::Confirmed)
                ->update($attributes) === 1;

            if (! $moved) {
                throw new AppointmentNotMovableException;
            }

            return $appointment->forceFill($attributes + ['status' => AppointmentStatus::Confirmed])->syncOriginal();
        });
    }
}
