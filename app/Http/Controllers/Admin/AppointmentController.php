<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CancelAppointment;
use App\Actions\CreateAppointment;
use App\Actions\RescheduleAppointment;
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
        return view('admin.appointments.create', [
            'services' => Service::query()->where('is_active', true)->ordered()->get(),
            'day' => AgendaController::dayFromQuery($request->query('fecha')),
        ]);
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
            ->route('admin.agenda', ['fecha' => $startsAt->toDateString()])
            ->with('status', 'Cita creada.');
    }

    public function edit(Appointment $appointment): View|RedirectResponse
    {
        if (! self::isMovable($appointment)) {
            return self::notMovableResponse($appointment);
        }

        return view('admin.appointments.edit', [
            'appointment' => $appointment,
            'services' => Service::query()
                ->where('is_active', true)
                ->orWhere('id', $appointment->service_id)
                ->ordered()
                ->get(),
        ]);
    }

    /**
     * A full or closed time is not saved until the salon confirms it after
     * the warning (see UpdateAdminAppointmentRequest::confirmsSlot()). The
     * customer is emailed only when the time or the service changes.
     */
    public function update(UpdateAdminAppointmentRequest $request, Appointment $appointment, RescheduleAppointment $rescheduleAppointment, AppointmentNotifier $notifier): RedirectResponse
    {
        $service = Service::findOrFail($request->validated('service_id'));
        $previousStart = $appointment->starts_at;
        $previousServiceId = (int) $appointment->service_id;

        try {
            $rescheduleAppointment->handle($appointment, $service, $request->startsAt(), $request->customer(), ignoreHoursAndCapacity: $request->confirmsSlot());
        } catch (AppointmentNotMovableException) {
            return self::notMovableResponse($appointment);
        } catch (StartTimeInPastException) {
            return back()->withInput($request->except('force'))->withErrors(['time' => 'Esa hora ya ha pasado.']);
        } catch (SlotUnavailableException) {
            return back()->withInput($request->except('force'))->with('slot_warning', $request->slotKey());
        }

        $redirect = redirect()->route('admin.agenda', ['fecha' => $appointment->starts_at->toDateString()]);
        $changed = ! $appointment->starts_at->eq($previousStart) || (int) $appointment->service_id !== $previousServiceId;

        if ($changed && ! $notifier->sendRescheduleNotice($appointment)) {
            return $redirect->with('warning', 'Cita actualizada, pero no se ha podido enviar el correo a la clienta con el cambio. Avísala por teléfono.');
        }

        return $redirect->with('status', $changed && $appointment->customer_email !== null
            ? 'Cita actualizada. Se ha enviado el cambio a la clienta por correo.'
            : 'Cita actualizada.');
    }

    public function cancel(Appointment $appointment, CancelAppointment $cancelAppointment, AppointmentNotifier $notifier): RedirectResponse
    {
        if ($cancelAppointment->handle($appointment)) {
            $notifier->sendCancellationNotices($appointment, cancelledByCustomer: false);
        }

        return redirect()
            ->route('admin.agenda', ['fecha' => $appointment->starts_at->toDateString()])
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

    private static function notMovableResponse(Appointment $appointment): RedirectResponse
    {
        return redirect()
            ->route('admin.agenda', ['fecha' => $appointment->starts_at->toDateString()])
            ->with('warning', 'Esta cita ya no se puede mover: está cancelada o ya ha empezado.');
    }
}
