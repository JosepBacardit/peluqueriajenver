<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CancelAppointment;
use App\Actions\CreateAppointment;
use App\Actions\RescheduleAppointment;
use App\Booking\AppointmentChangedException;
use App\Booking\AppointmentNotifier;
use App\Booking\AppointmentNotMovableException;
use App\Booking\DuplicateAppointmentException;
use App\Booking\SlotUnavailableException;
use App\Booking\StartTimeInPastException;
use App\Enums\AppointmentSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminAppointmentRequest;
use App\Http\Requests\Admin\UpdateAdminAppointmentRequest;
use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Appointments recorded, moved or cancelled by the salon. Appointments are
 * never deleted.
 */
class AppointmentController extends Controller
{
    public function create(Request $request): View
    {
        $services = Service::query()->where('is_active', true)->ordered()->get();

        return view('admin.appointments.create', [
            'services' => $services,
            'day' => AgendaController::dayFromQuery($request->query('fecha')),
            // Review finding M1: where to return to after saving, instead
            // of always landing on vista Día.
            'volver' => self::volverParam($request->query('volver')),
            // PRF-114: prefilled from a free slot tapped on the timeline
            // grid; a missing or malformed value just leaves the field
            // empty, same as before this existed.
            'time' => self::timeParam($request->query('hora')),
            // PRF-120 (T045): preselects the service the agenda was
            // filtered by, or the one whose "Cabe" hueco was tapped —
            // validated against the same active list the <select> itself
            // renders, so an invalid/inactive id is silently ignored
            // rather than preselecting nothing with an error.
            'servicio' => AgendaController::servicioFromQuery($request->query('servicio'), $services)?->id,
        ]);
    }

    /**
     * A valid "HH:MM" (00-23:00-59), or null — never trusts the raw query
     * value past this check.
     */
    private static function timeParam(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : null;
    }

    public function store(StoreAdminAppointmentRequest $request, CreateAppointment $createAppointment, AppointmentNotifier $notifier): RedirectResponse
    {
        $service = Service::findOrFail($request->validated('service_id'));
        $startsAt = $request->startsAt();

        try {
            $appointment = $createAppointment->handle($service, $startsAt, $request->customer(), AppointmentSource::Admin, applyPublicRules: false);
        } catch (SlotUnavailableException) {
            return back()->withInput()->withErrors(['time' => 'Esa hora no está disponible para este servicio.']);
        } catch (DuplicateAppointmentException) {
            return back()->withInput()->withErrors(['customer_email' => 'Este email ya tiene una cita confirmada a esa hora.']);
        }

        $notifier->sendCreationNotices($appointment);

        return redirect()
            ->route('admin.agenda', self::volverFromRequest($request) ?? ['fecha' => $startsAt->toDateString()])
            ->with('status', 'Cita creada.');
    }

    public function edit(Request $request, Appointment $appointment): View|RedirectResponse
    {
        if (! self::isMovable($appointment)) {
            return self::notMovableResponse($appointment, $request);
        }

        return view('admin.appointments.edit', [
            'appointment' => $appointment,
            'services' => Service::query()
                ->where('is_active', true)
                ->orWhere('id', $appointment->service_id)
                ->ordered()
                ->get(),
            'volver' => self::volverParam($request->query('volver')),
        ]);
    }

    /**
     * A full or closed time is not saved until the salon confirms it after
     * the warning (see UpdateAdminAppointmentRequest::confirmsSlot()). The
     * customer gets one email when the time or the service changes (the
     * change notice) or when only her email changes (her appointment with
     * the new personal link); RescheduleAppointment decides both under its
     * lock. The email is built from the values just saved.
     */
    public function update(UpdateAdminAppointmentRequest $request, Appointment $appointment, RescheduleAppointment $rescheduleAppointment, AppointmentNotifier $notifier): RedirectResponse
    {
        $service = Service::findOrFail($request->validated('service_id'));

        try {
            $outcome = $rescheduleAppointment->handle(
                $appointment, $service, $request->startsAt(), $request->customer(),
                ignoreHoursAndCapacity: $request->confirmsSlot(),
                expectedVersion: $request->version(),
            );
        } catch (AppointmentNotMovableException) {
            return self::notMovableResponse($appointment, $request);
        } catch (AppointmentChangedException) {
            return redirect()
                ->route('admin.appointments.edit', array_filter(['appointment' => $appointment, 'volver' => self::volverParam($request->input('volver'))]))
                ->with('warning', 'Otra persona ha cambiado esta cita mientras la editabas, así que no se ha guardado nada. Estos son sus datos actuales: repite tu cambio si sigue haciendo falta.');
        } catch (StartTimeInPastException) {
            return back()->withInput($request->except('force'))->withErrors(['time' => 'Esa hora ya ha pasado.']);
        } catch (SlotUnavailableException $exception) {
            return back()->withInput($request->except('force'))->with('slot_warning', [
                'key' => $request->slotKey(),
                'reason' => $exception->reason?->value,
            ]);
        }

        // Review finding M1: return to the view/date the salon was on
        // (vista Semana/Mes), not always vista Día.
        $redirect = redirect()->route('admin.agenda', self::volverFromRequest($request) ?? ['fecha' => $appointment->starts_at->toDateString()]);

        if ((! $outcome->rescheduled && ! $outcome->emailChanged) || $appointment->customer_email === null) {
            return $redirect->with('status', 'Cita actualizada.');
        }

        if (! $notifier->sendChangeNotice($appointment, $outcome->rescheduled)) {
            return $redirect->with('warning', $outcome->emailChanged
                ? 'Cita actualizada, pero no se ha podido enviar a la clienta el correo con su nuevo enlace, y el anterior ya no funciona. El sistema lo reintentará cada 10 minutos; si no le llega, avísala por teléfono.'
                : 'Cita actualizada, pero no se ha podido enviar el correo a la clienta con el cambio. Avísala por teléfono.');
        }

        return $redirect->with('status', $outcome->emailChanged
            ? 'Cita actualizada. Se ha enviado a la clienta un correo con su cita y su nuevo enlace.'
            : 'Cita actualizada. Se ha enviado el cambio a la clienta por correo.');
    }

    public function cancel(Request $request, Appointment $appointment, CancelAppointment $cancelAppointment, AppointmentNotifier $notifier): RedirectResponse
    {
        if ($cancelAppointment->handle($appointment)) {
            $notifier->sendCancellationNotices($appointment, cancelledByCustomer: false);
        }

        return redirect()
            ->route('admin.agenda', self::volverFromRequest($request) ?? ['fecha' => $appointment->starts_at->toDateString()])
            ->with('status', 'Cita cancelada.');
    }

    /**
     * Only confirmed appointments that have not started yet can be moved.
     * RescheduleAppointment re-checks this under its lock; this check only
     * keeps the edit form from being shown for the others.
     */
    private static function isMovable(Appointment $appointment): bool
    {
        return $appointment->isConfirmed() && $appointment->starts_at->isFuture();
    }

    private static function notMovableResponse(Appointment $appointment, Request $request): RedirectResponse
    {
        return redirect()
            ->route('admin.agenda', self::volverFromRequest($request) ?? ['fecha' => $appointment->starts_at->toDateString()])
            ->with('warning', 'Esta cita ya no se puede mover: está cancelada o ya ha empezado.');
    }

    /**
     * The validated "vista:fecha" string (review finding M1) to echo into
     * a hidden form field, or null when missing/invalid.
     */
    private static function volverParam(mixed $value): ?string
    {
        $volver = AgendaController::volverFromQuery($value);

        return $volver === null ? null : "{$volver['vista']}:{$volver['fecha']}";
    }

    /**
     * The validated ['vista' => ..., 'fecha' => ...] route params from the
     * request's "volver" field (Request::input() reads both the query
     * string and the POST/PUT body, so this works for every action), or
     * null when missing/invalid — never an arbitrary redirect target.
     */
    private static function volverFromRequest(Request $request): ?array
    {
        return AgendaController::volverFromQuery($request->input('volver'));
    }
}
