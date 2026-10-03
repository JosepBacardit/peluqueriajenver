<x-mail::message>
# Un cliente ha cancelado su cita

- **Servicio:** {{ $appointment->service_name }}
- **Día:** {{ ucfirst($appointment->dayLabel()) }}
- **Hora:** {{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }}
- **Nombre:** {{ $appointment->customer_name }}
- **Teléfono:** {{ $appointment->customer_phone }}

La hora vuelve a estar disponible para reservar.

<x-mail::button :url="$agendaUrl">
Ver la agenda de ese día
</x-mail::button>
</x-mail::message>
