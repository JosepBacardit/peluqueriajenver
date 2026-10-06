<?php

namespace App\Actions;

use App\Booking\AvailabilityCalculator;
use App\Booking\DuplicateAppointmentException;
use App\Booking\ServiceList;
use App\Booking\SlotUnavailableException;
use App\Booking\TooManyUpcomingAppointmentsException;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\BookingSetting;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateAppointment
{
    public const MAX_UPCOMING_ONLINE = 2;

    public function __construct(private AvailabilityCalculator $calculator) {}

    /**
     * Creates a confirmed appointment if the slot is still available.
     *
     * Every booking takes a row lock on the single booking_settings row
     * before re-checking availability, so concurrent bookings (web or
     * admin) are serialised and two of them can never both take the last
     * place. Traffic is a handful of bookings a day, so the global lock
     * costs nothing noticeable. (SQLite, used in tests, ignores FOR UPDATE
     * but serialises writes anyway.)
     *
     * The locking read MUST be the first query of the transaction. Under
     * MySQL/InnoDB REPEATABLE READ, the snapshot used by the later plain
     * reads (duplicate check, availability) is taken at the first
     * non-locking read; because that happens only after the lock is
     * granted, a request that waited for the lock sees the appointment
     * the previous request just committed. Reading anything before the
     * lock (or starting the transaction WITH CONSISTENT SNAPSHOT) would
     * freeze an older snapshot and reopen overbooking. A test asserts the
     * lock comes first.
     *
     * $services are the 1 to Appointment::MAX_SERVICES services booked
     * together (PRF-125): they are done one after another in the salon's
     * order (ServiceList::ordered()), so availability is checked for the
     * sum of their durations and the appointment takes one place for all
     * of it. The appointment and its frozen copies of the services
     * (appointment_services, PRF-126) are written in this same transaction,
     * after the lock, so a booking never exists without its services.
     *
     * @param  Collection<int, Service>  $services
     * @param  array{customer_name: string, customer_phone: string, customer_email: string|null, notes: string|null}  $customer
     *
     * @throws InvalidArgumentException when $services is empty, over the maximum or repeats a service
     * @throws SlotUnavailableException
     * @throws DuplicateAppointmentException
     * @throws TooManyUpcomingAppointmentsException
     */
    public function handle(
        Collection $services,
        CarbonImmutable $startsAt,
        array $customer,
        AppointmentSource $source,
        bool $applyPublicRules,
        ?CarbonImmutable $now = null,
    ): Appointment {
        $now ??= CarbonImmutable::now();
        $email = $customer['customer_email'] === null ? null : Str::lower(trim($customer['customer_email']));
        $services = ServiceList::ordered($services);
        $durationMinutes = (int) $services->sum('duration_minutes');

        return DB::transaction(function () use ($services, $durationMinutes, $startsAt, $customer, $source, $applyPublicRules, $now, $email): Appointment {
            BookingSetting::query()->lockForUpdate()->orderBy('id')->firstOrFail();

            if ($email !== null && Appointment::query()->confirmed()
                ->where('customer_email', $email)
                ->where('starts_at', $startsAt)
                ->exists()) {
                throw new DuplicateAppointmentException;
            }

            if ($applyPublicRules && $this->hasTooManyUpcoming($email, $customer['customer_phone'], $now)) {
                throw new TooManyUpcomingAppointmentsException;
            }

            if (! $this->calculator->isAvailable($durationMinutes, $startsAt, $now, $applyPublicRules)) {
                throw new SlotUnavailableException;
            }

            $appointment = Appointment::create([
                'services_label' => ServiceList::label($services->pluck('name')),
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($durationMinutes),
                'customer_name' => trim($customer['customer_name']),
                'customer_phone' => trim($customer['customer_phone']),
                'customer_email' => $email,
                'notes' => $customer['notes'] === null ? null : trim($customer['notes']),
                'status' => AppointmentStatus::Confirmed,
                'source' => $source,
                'token' => Str::random(48),
                'privacy_accepted_at' => $source === AppointmentSource::Web ? $now : null,
            ]);

            $appointment->items()->createMany(
                $services->map(fn (Service $service, int $index) => AppointmentService::snapshotOf($service, $index + 1))->all(),
            );

            return $appointment;
        });
    }

    /**
     * Online bookings are capped per customer so one person (or script)
     * cannot fill the agenda: the same email, or the same phone whatever
     * its formatting, may hold at most MAX_UPCOMING_ONLINE upcoming
     * confirmed appointments. Phones are compared by their last 9 digits,
     * so "+34 600-123-456" and "600 12 34 56" match.
     */
    private function hasTooManyUpcoming(?string $email, string $phone, CarbonImmutable $now): bool
    {
        $phoneKey = self::phoneKey($phone);

        $matching = Appointment::query()
            ->confirmed()
            ->where('starts_at', '>', $now)
            ->get(['customer_email', 'customer_phone'])
            ->filter(fn (Appointment $appointment) => ($email !== null && $appointment->customer_email === $email)
                || self::phoneKey($appointment->customer_phone) === $phoneKey);

        return $matching->count() >= self::MAX_UPCOMING_ONLINE;
    }

    private static function phoneKey(string $phone): string
    {
        return substr((string) preg_replace('/\D/', '', $phone), -9);
    }
}
