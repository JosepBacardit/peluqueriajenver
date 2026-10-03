<?php

namespace App\Actions;

use App\Booking\AvailabilityCalculator;
use App\Booking\DuplicateAppointmentException;
use App\Booking\SlotUnavailableException;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAppointment
{
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
     * @param  array{customer_name: string, customer_phone: string, customer_email: string|null, notes: string|null}  $customer
     *
     * @throws SlotUnavailableException
     * @throws DuplicateAppointmentException
     */
    public function handle(
        Service $service,
        CarbonImmutable $startsAt,
        array $customer,
        AppointmentSource $source,
        bool $applyPublicRules,
        ?CarbonImmutable $now = null,
    ): Appointment {
        $now ??= CarbonImmutable::now();
        $email = $customer['customer_email'] === null ? null : Str::lower(trim($customer['customer_email']));

        return DB::transaction(function () use ($service, $startsAt, $customer, $source, $applyPublicRules, $now, $email): Appointment {
            BookingSetting::query()->lockForUpdate()->orderBy('id')->firstOrFail();

            if ($email !== null && Appointment::query()->confirmed()
                ->where('customer_email', $email)
                ->where('starts_at', $startsAt)
                ->exists()) {
                throw new DuplicateAppointmentException;
            }

            if (! $this->calculator->isAvailable($service->duration_minutes, $startsAt, $now, $applyPublicRules)) {
                throw new SlotUnavailableException;
            }

            return Appointment::create([
                'service_id' => $service->id,
                'service_name' => $service->name,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($service->duration_minutes),
                'customer_name' => trim($customer['customer_name']),
                'customer_phone' => trim($customer['customer_phone']),
                'customer_email' => $email,
                'notes' => $customer['notes'] === null ? null : trim($customer['notes']),
                'status' => AppointmentStatus::Confirmed,
                'source' => $source,
                'token' => Str::random(48),
                'privacy_accepted_at' => $source === AppointmentSource::Web ? $now : null,
            ]);
        });
    }
}
