<?php

namespace App\Actions;

use App\Booking\AppointmentChangedException;
use App\Booking\AppointmentNotMovableException;
use App\Booking\AvailabilityCalculator;
use App\Booking\RescheduleOutcome;
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
 * same row is updated: its id never changes, nor does its token (the
 * customer's personal link) unless the email changes, and nothing is
 * written about the change itself.
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
     * $expectedVersion is the appointment's updated_at (as a Unix
     * timestamp) shown in the salon's edit form. It is compared under the
     * lock, so a form opened before someone else changed the appointment
     * is refused instead of silently undoing that change (the form sends
     * every field back, not only the ones edited). updated_at has a
     * one-second resolution: a change made in the same second the form
     * was opened would go unnoticed, which the salon's traffic makes
     * negligible. Null skips the check.
     *
     * The appointment itself never counts against its new time
     * (excludeAppointmentId), so it can be moved into the time it holds
     * now, and when neither its time nor its length changes availability
     * is not checked at all: editing only the customer's details of an
     * appointment saved over capacity, or before the schedule changed,
     * needs no new confirmation. $ignoreHoursAndCapacity is the salon's
     * explicit "save anyway" after being warned that the new time is full
     * or outside opening hours; a start time in the past is refused even
     * then.
     *
     * When the service does not change, the appointment keeps the name and
     * duration it was booked with (as when the service is edited later);
     * a different service brings its own current name and duration.
     *
     * A different email address gets a new token: whoever received the
     * old personal link (e.g. at a mistyped address) can no longer see or
     * cancel the appointment. Its confirmation is left pending
     * (customer_notified_at = null) until the new address is emailed, so
     * appointments:notify-pending resends it if that email fails.
     *
     * @param  array{customer_name: string, customer_phone: string, customer_email: string|null, notes: string|null}  $customer
     *
     * @throws AppointmentNotMovableException
     * @throws AppointmentChangedException
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
        ?int $expectedVersion = null,
    ): RescheduleOutcome {
        $now ??= CarbonImmutable::now();

        return DB::transaction(function () use ($appointment, $service, $startsAt, $customer, $ignoreHoursAndCapacity, $now, $expectedVersion): RescheduleOutcome {
            BookingSetting::query()->lockForUpdate()->orderBy('id')->firstOrFail();

            $current = Appointment::query()->find($appointment->getKey());

            if ($current === null || ! $current->isConfirmed() || $current->starts_at->lte($now)) {
                throw new AppointmentNotMovableException;
            }

            if ($expectedVersion !== null && $current->updated_at?->getTimestamp() !== $expectedVersion) {
                throw new AppointmentChangedException;
            }

            if ($startsAt->lt($now)) {
                throw new StartTimeInPastException;
            }

            $keepsService = (int) $current->service_id === (int) $service->id;
            $currentDurationMinutes = (int) $current->starts_at->diffInMinutes($current->ends_at);
            $durationMinutes = $keepsService ? $currentDurationMinutes : $service->duration_minutes;
            $keepsSlot = $startsAt->eq($current->starts_at) && $durationMinutes === $currentDurationMinutes;

            if (! $ignoreHoursAndCapacity && ! $keepsSlot) {
                $reason = $this->calculator->unavailabilityReason($durationMinutes, $startsAt, $now, applyPublicRules: false, excludeAppointmentId: $current->id);

                if ($reason !== null) {
                    throw new SlotUnavailableException($reason);
                }
            }

            $email = $customer['customer_email'] === null ? null : Str::lower(trim($customer['customer_email']));
            $emailChanged = $email !== $current->customer_email;

            $attributes = [
                'service_id' => $service->id,
                'service_name' => $keepsService ? $current->service_name : $service->name,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($durationMinutes),
                'customer_name' => trim($customer['customer_name']),
                'customer_phone' => trim($customer['customer_phone']),
                'customer_email' => $email,
                'notes' => $customer['notes'] === null ? null : trim($customer['notes']),
                'updated_at' => $current->freshTimestamp(),
            ];

            if ($emailChanged) {
                $attributes['token'] = Str::random(48);
                $attributes['customer_notified_at'] = null;
            }

            $moved = Appointment::query()
                ->whereKey($current->getKey())
                ->where('status', AppointmentStatus::Confirmed)
                ->update($attributes) === 1;

            if (! $moved) {
                throw new AppointmentNotMovableException;
            }

            return new RescheduleOutcome(
                appointment: $appointment->forceFill($attributes + ['status' => AppointmentStatus::Confirmed])->syncOriginal(),
                rescheduled: ! $startsAt->eq($current->starts_at) || ! $keepsService,
                emailChanged: $emailChanged,
            );
        });
    }
}
