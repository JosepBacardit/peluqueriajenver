<?php

namespace App\Http\Controllers;

use App\Actions\CreateAppointment;
use App\Booking\AppointmentNotifier;
use App\Booking\AvailabilityCalculator;
use App\Booking\DuplicateAppointmentException;
use App\Booking\SlotUnavailableException;
use App\Booking\TooManyUpcomingAppointmentsException;
use App\Enums\AppointmentSource;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public booking page, rendered entirely on the server: choose a service
 * (?servicio=), a day in the month calendar (?mes=Y-m&fecha=Y-m-d), then
 * a free time and the customer's details.
 */
class BookingController extends Controller
{
    public function __construct(private AvailabilityCalculator $calculator) {}

    public function index(Request $request): View
    {
        $services = Service::query()->bookableOnline()->ordered()->get();
        $service = $services->firstWhere('id', (int) $request->query('servicio'));

        if ($service === null) {
            return view('pages.reservas', ['services' => $services, 'service' => null]);
        }

        $now = CarbonImmutable::now();
        $firstMonth = $now->startOfMonth();
        $lastDay = $this->calculator->lastBookableDay($now);
        $lastMonth = $lastDay->startOfMonth();

        $requestedDay = self::parseDate($request->query('fecha'));
        $month = self::parseMonth($request->query('mes')) ?? $requestedDay?->startOfMonth() ?? $firstMonth;
        $month = $month->lt($firstMonth) ? $firstMonth : ($month->gt($lastMonth) ? $lastMonth : $month);

        $availableDays = $this->calculator->daysWithAvailability(
            $service->duration_minutes,
            $month->lt($now->startOfDay()) ? $now->startOfDay() : $month,
            $month->endOfMonth()->lt($lastDay) ? $month->endOfMonth()->startOfDay() : $lastDay,
            $now,
        );

        $day = $requestedDay !== null && $requestedDay->isSameMonth($month) && in_array($requestedDay->toDateString(), $availableDays, true)
            ? $requestedDay
            : null;

        return view('pages.reservas', [
            'services' => $services,
            'service' => $service,
            'month' => $month,
            'previousMonth' => $month->gt($firstMonth) ? $month->subMonth() : null,
            'nextMonth' => $month->lt($lastMonth) ? $month->addMonth() : null,
            'availableDays' => $availableDays,
            'requestedDay' => $requestedDay,
            'day' => $day,
            'times' => $day === null ? [] : $this->calculator->availableStartTimes($service->duration_minutes, $day, $now),
        ]);
    }

    public function store(StoreBookingRequest $request, CreateAppointment $createAppointment, AppointmentNotifier $notifier): RedirectResponse
    {
        $startsAt = $request->startsAt();
        $service = Service::query()->bookableOnline()->find($request->validated('service_id'));
        $backToDay = route('reservas', [
            'servicio' => $request->validated('service_id'),
            'mes' => $startsAt->format('Y-m'),
            'fecha' => $startsAt->toDateString(),
        ]).'#horas';

        try {
            if ($service === null) {
                throw new SlotUnavailableException;
            }

            $appointment = $createAppointment->handle(collect([$service]), $startsAt, $request->customer(), AppointmentSource::Web, applyPublicRules: true);
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
