<?php

namespace App\Http\Controllers;

use App\Actions\CreateAppointment;
use App\Booking\AppointmentNotifier;
use App\Booking\AvailabilityCalculator;
use App\Booking\DuplicateAppointmentException;
use App\Booking\ServiceList;
use App\Booking\SlotUnavailableException;
use App\Booking\TooManyUpcomingAppointmentsException;
use App\Enums\AppointmentSource;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Public booking page, rendered entirely on the server: choose 1 to
 * Appointment::MAX_SERVICES services (?servicio[]=, or the legacy scalar
 * ?servicio=, PRF-127), a day in the month calendar
 * (?mes=Y-m&fecha=Y-m-d), then a free time and the customer's details.
 */
class BookingController extends Controller
{
    public function __construct(private AvailabilityCalculator $calculator) {}

    public function index(Request $request): View
    {
        // "Reserva online activa" (Ajustes) and at least one bookable-
        // online service (PRF-147): /reservas keeps its URL and answers
        // 200 either way, so it never breaks a link or a bookmark — it
        // just shows a phone/WhatsApp page instead of the form while
        // either is missing.
        if (! BookingSetting::onlineBookingAvailable()) {
            return view('pages.reservas-disabled');
        }

        $services = Service::query()->bookableOnline()->ordered()->get();
        ['ids' => $requestedIds, 'adjusted' => $selectionAdjusted] = self::requestedServiceIds($request->query('servicio'));
        $selectedServices = self::selectedServices($requestedIds, $services);

        // Step 1 also when "Cambiar" was pressed on step 2 (cambiar=1,
        // review finding L6): the choice comes back already checked.
        if ($selectedServices->isEmpty() || $request->boolean('cambiar')) {
            return view('pages.reservas', [
                'services' => $services,
                'selectedServices' => collect(),
                // A notice only once something was actually tried and
                // failed (too many, none matching) — not on a first,
                // empty visit to the page, nor when coming back to change
                // a valid choice.
                'invalidSelection' => $selectedServices->isEmpty() && $requestedIds !== [],
                'checkedIds' => $requestedIds,
            ]);
        }

        $durationMinutes = (int) $selectedServices->sum('duration_minutes');
        $now = CarbonImmutable::now();
        $firstMonth = $now->startOfMonth();
        $lastDay = $this->calculator->lastBookableDay($now);
        $lastMonth = $lastDay->startOfMonth();

        $requestedDay = self::parseDate($request->query('fecha'));
        $month = self::parseMonth($request->query('mes')) ?? $requestedDay?->startOfMonth() ?? $firstMonth;
        $month = $month->lt($firstMonth) ? $firstMonth : ($month->gt($lastMonth) ? $lastMonth : $month);

        $availableDays = $this->calculator->daysWithAvailability(
            $durationMinutes,
            $month->lt($now->startOfDay()) ? $now->startOfDay() : $month,
            $month->endOfMonth()->lt($lastDay) ? $month->endOfMonth()->startOfDay() : $lastDay,
            $now,
        );

        $day = $requestedDay !== null && $requestedDay->isSameMonth($month) && in_array($requestedDay->toDateString(), $availableDays, true)
            ? $requestedDay
            : null;

        return view('pages.reservas', [
            'services' => $services,
            'selectedServices' => $selectedServices,
            'checkedIds' => $selectedServices->pluck('id')->all(),
            'invalidSelection' => false,
            'selectionAdjusted' => $selectionAdjusted,
            // The "servicio" query fragment every link on step 2+ (the
            // calendar's day/month links, "Cambiar") splats in — a scalar
            // when there is only one (keeping that link exactly as short
            // as the legacy single-service one, PRF-127), the full array
            // otherwise.
            'servicioQuery' => ['servicio' => self::servicioRouteParam($selectedServices->pluck('id')->all())],
            'month' => $month,
            'previousMonth' => $month->gt($firstMonth) ? $month->subMonth() : null,
            'nextMonth' => $month->lt($lastMonth) ? $month->addMonth() : null,
            'availableDays' => $availableDays,
            'requestedDay' => $requestedDay,
            'day' => $day,
            'times' => $day === null ? [] : $this->calculator->availableStartTimes($durationMinutes, $day, $now),
        ]);
    }

    /**
     * Ids from "servicio" — a scalar (the legacy single-service link,
     * PRF-127) or an array — or [] when missing. PRF-127 (review finding
     * L1): a repeated id or a value that is not an id is normalised away
     * here (never trusted past this point), and 'adjusted' says so, so
     * step 2 can show a discreet notice instead of a broken page. More
     * than MAX_SERVICES distinct ids, or ids that are not reservable
     * online, are not normalised: selectedServices() rejects them whole.
     *
     * @return array{ids: list<int>, adjusted: bool}
     */
    private static function requestedServiceIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['ids' => [], 'adjusted' => false];
        }

        $raw = is_array($value) ? array_values($value) : [$value];
        $ids = array_values(array_unique(array_map('intval', array_filter($raw, fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id))))));

        return ['ids' => $ids, 'adjusted' => count($ids) !== count($raw)];
    }

    /**
     * The services for $ids, in the salon's order (PRF-125,
     * ServiceList::sort(), the same order the booking is saved in) — or
     * empty when $ids is
     * empty, has more than MAX_SERVICES entries, or any of them is not a
     * reservable-online service: PRF-127 rejects such a selection whole,
     * never silently drops the bad ones and keeps the rest.
     *
     * @param  list<int>  $ids
     * @param  Collection<int, Service>  $services
     * @return Collection<int, Service>
     */
    private static function selectedServices(array $ids, Collection $services): Collection
    {
        if ($ids === [] || count($ids) > Appointment::MAX_SERVICES) {
            return collect();
        }

        $matched = $services->whereIn('id', $ids)->values();

        return $matched->count() === count($ids) ? ServiceList::sort($matched) : collect();
    }

    /**
     * A single id as itself (so the link stays exactly as short as the
     * legacy one-service link, PRF-127), the whole list otherwise.
     *
     * @param  list<int>  $ids
     */
    private static function servicioRouteParam(array $ids): int|array
    {
        $ids = array_values($ids);

        return count($ids) === 1 ? $ids[0] : $ids;
    }

    public function store(StoreBookingRequest $request, CreateAppointment $createAppointment, AppointmentNotifier $notifier): RedirectResponse
    {
        $startsAt = $request->startsAt();
        $serviceIds = array_values($request->validated('service_ids'));
        $services = Service::query()->bookableOnline()->whereIn('id', $serviceIds)->get();
        $backToDay = route('reservas', [
            'servicio' => self::servicioRouteParam($serviceIds),
            'mes' => $startsAt->format('Y-m'),
            'fecha' => $startsAt->toDateString(),
        ]).'#horas';

        try {
            // PRF-128: any id that does not exist or is not reservable
            // online rejects the whole booking, exactly as a single
            // invalid service already did.
            if ($services->count() !== count($serviceIds)) {
                throw new SlotUnavailableException;
            }

            $appointment = $createAppointment->handle($services, $startsAt, $request->customer(), AppointmentSource::Web, applyPublicRules: true);
        } catch (SlotUnavailableException) {
            return redirect()->to($backToDay)->withInput()->withErrors(['time' => __('reservas.messages.slot_unavailable')]);
        } catch (DuplicateAppointmentException) {
            return redirect()->to($backToDay)->withInput()->withErrors(['customer_email' => __('reservas.messages.duplicate')]);
        } catch (TooManyUpcomingAppointmentsException) {
            return redirect()->to($backToDay)->withInput()->withErrors(['customer_email' => __('reservas.messages.too_many_upcoming')]);
        }

        $notifier->sendCreationNotices($appointment);

        return redirect()
            ->route('cita.show', $appointment->token)
            ->with('status', __('reservas.messages.confirmed'));
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== null && $date->format('Y-m-d') === $value ? $date : null;
    }

    private static function parseMonth(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return null;
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $value);

        return $month !== null && $month->format('Y-m') === $value ? $month : null;
    }
}
