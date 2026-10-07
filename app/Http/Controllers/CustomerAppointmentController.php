<?php

namespace App\Http\Controllers;

use App\Actions\CancelAppointment;
use App\Booking\AppointmentNotifier;
use App\Models\Appointment;
use App\Models\BookingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The customer's own appointment page, reached through the personal,
 * unguessable link sent by email (a random 48-character token stored with
 * the appointment, so the link does not depend on APP_KEY).
 */
class CustomerAppointmentController extends Controller
{
    public function show(string $token): View
    {
        $appointment = Appointment::query()->where('token', $token)->firstOrFail();
        $settings = BookingSetting::current();

        return view('pages.cita', [
            'appointment' => $appointment,
            'canCancel' => self::canCancel($appointment, $settings),
            'cancellationLimitHours' => $settings->cancellation_limit_hours,
        ]);
    }

    public function cancel(Request $request, string $token, CancelAppointment $cancelAppointment, AppointmentNotifier $notifier): RedirectResponse
    {
        $appointment = Appointment::query()->where('token', $token)->firstOrFail();

        if (! $appointment->isConfirmed()) {
            return redirect()->route('cita.show', $token);
        }

        if (! self::canCancel($appointment, BookingSetting::current())) {
            return redirect()->route('cita.show', $token)
                ->withErrors(['confirm' => __('reservas.messages.too_late_to_cancel')]);
        }

        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => __('reservas.appointment.cancel_confirm_required')]);

        if ($cancelAppointment->handle($appointment)) {
            $notifier->sendCancellationNotices($appointment, cancelledByCustomer: true);
        }

        return redirect()->route('cita.show', $token)->with('status', __('reservas.messages.cancelled_now'));
    }

    /**
     * Online cancellation is allowed until `cancellation_limit_hours`
     * before the start (exactly at the limit still counts).
     */
    private static function canCancel(Appointment $appointment, BookingSetting $settings): bool
    {
        return $appointment->isConfirmed()
            && now()->lte($appointment->starts_at->subHours($settings->cancellation_limit_hours));
    }
}
