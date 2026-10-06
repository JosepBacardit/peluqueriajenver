<?php

namespace App\Booking;

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The services of one appointment (PRF-125): 1 to Appointment::MAX_SERVICES
 * distinct services, done one after another in the salon's order (sort(),
 * whatever order they were chosen in). Shared by
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

        return self::sort($services);
    }

    /**
     * The salon's order of an appointment's services: "Orden" (sort_order),
     * then the order they were created in (id). The single criterion for
     * every place that puts an appointment's services in order — saving
     * them, the summary on /reservas, the agenda's "Cabe" selection — so
     * they can never disagree (review finding L2). Names are deliberately
     * not compared: PHP and MySQL collate them differently (case and
     * accents), while integers order the same everywhere. Catalogue lists
     * (Service::scopeOrdered(), PRF-012) still sort by name for display.
     *
     * @param  iterable<Service>  $services
     * @return Collection<int, Service>
     */
    public static function sort(iterable $services): Collection
    {
        return collect($services)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();
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
