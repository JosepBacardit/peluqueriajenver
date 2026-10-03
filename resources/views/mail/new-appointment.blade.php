<x-mail::message>
# Nueva cita online

- **Servicio:** {{ $appointment->service_name }}
- **Día:** {{ ucfirst($appointment->dayLabel()) }}
- **Hora:** {{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }}
- **Nombre:** {{ $appointment->customer_name }}
- **Teléfono:** {{ $appointment->customer_phone }}
- **Email:** {{ $appointment->customer_email }}
@if ($appointment->notes)
- **Observaciones:** {{ $appointment->notes }}
@endif

<x-mail::button :url="$agendaUrl">
Ver la agenda de ese día
</x-mail::button>
</x-mail::message>
