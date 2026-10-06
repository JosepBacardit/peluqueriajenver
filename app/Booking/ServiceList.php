<?php

namespace App\Booking;

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The services of one appointment (PRF-125): 1 to Appointment::MAX_SERVICES
 * distinct services, done one after another in the salon's order (sort
 * order, then name), whatever order they were chosen in. Shared by
 * CreateAppointment and RescheduleAppointment so both apply the same rules;
 * the form requests reject a bad list first, so a bad list reaching here
 * is a programming error.
 */
final class ServiceList
{
    /**
     * @param  iterable<Service>  $services
     * @return Collection<int, Service> in the salon's order
     *
     * @throws InvalidArgumentException when empty, over the maximum or with a service repeated
     */
    public static function ordered(iterable $services): Collection
    {
        $services = collect($services)->values();

        if ($services->isEmpty() || $services->count() > Appointment::MAX_SERVICES) {
            throw new InvalidArgumentException('An appointment needs 1 to '.Appointment::MAX_SERVICES.' services.');
        }

        if ($services->pluck('id')->unique()->count() !== $services->count()) {
            throw new InvalidArgumentException('An appointment cannot hold the same service twice.');
        }

        return $services->sortBy([['sort_order', 'asc'], ['name', 'asc'], ['id', 'asc']])->values();
    }

    /**
     * services_label: the names joined in order, e.g. "Corte + Barba".
     *
     * @param  iterable<string>  $names
     */
    public static function label(iterable $names): string
    {
        return collect($names)->implode(Appointment::SERVICES_LABEL_SEPARATOR);
    }
}
