<x-mail::message>
@if ($cancelledByCustomer)
# Tu cita se ha cancelado

Hola, {{ $appointment->customer_name }}:

Te confirmamos que has cancelado tu cita.
@else
# Hemos tenido que cancelar tu cita

Hola, {{ $appointment->customer_name }}:

Lo sentimos: el salón ha tenido que cancelar tu cita. Puedes reservar otra hora cuando quieras o llamarnos al 633 912 050.
@endif

@include('mail.partials.appointment-services')
- **Día:** {{ ucfirst($appointment->dayLabel()) }}
- **Hora:** {{ $appointment->starts_at->format('H:i') }}

<x-mail::button :url="$bookingUrl">
Reservar otra cita
</x-mail::button>

Peluquería Jenver
</x-mail::message>
