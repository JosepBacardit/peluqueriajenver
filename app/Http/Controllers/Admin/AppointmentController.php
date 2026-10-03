<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CancelAppointment;
use App\Actions\CreateAppointment;
use App\Booking\DuplicateAppointmentException;
use App\Booking\SlotUnavailableException;
use App\Enums\AppointmentSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminAppointmentRequest;
use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Appointments recorded or cancelled by the salon. Appointments are never
 * deleted.
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

    public function store(StoreAdminAppointmentRequest $request, CreateAppointment $createAppointment): RedirectResponse
    {
        $service = Service::findOrFail($request->validated('service_id'));
        $startsAt = $request->startsAt();

        try {
            $createAppointment->handle($service, $startsAt, $request->customer(), AppointmentSource::Admin, applyPublicRules: false);
        } catch (SlotUnavailableException) {
            return back()->withInput()->withErrors(['time' => 'Esa hora no está disponible para este servicio.']);
        } catch (DuplicateAppointmentException) {
            return back()->withInput()->withErrors(['customer_email' => 'Este email ya tiene una cita confirmada a esa hora.']);
        }

        return redirect()
            ->route('admin.agenda', ['fecha' => $startsAt->toDateString()])
            ->with('status', 'Cita creada.');
    }

    public function cancel(Appointment $appointment, CancelAppointment $cancelAppointment): RedirectResponse
    {
        $cancelAppointment->handle($appointment);

        return redirect()
            ->route('admin.agenda', ['fecha' => $appointment->starts_at->toDateString()])
            ->with('status', 'Cita cancelada.');
    }
}
