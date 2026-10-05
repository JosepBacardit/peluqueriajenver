<x-mail::message>
# Tu cita está confirmada

Hola, {{ $appointment->customer_name }}:

Te esperamos en Peluquería Jenver.

- **Servicio:** {{ $appointment->service_name }}
- **Día:** {{ ucfirst($appointment->dayLabel()) }}
- **Hora:** {{ $appointment->starts_at->format('H:i') }}
- **Dónde:** C/ Lleida, 21 · 08110 Montcada i Reixac
- **Teléfono:** 633 912 050

Desde este enlace puedes ver tu cita y, si no puedes venir, cancelarla:

<x-mail::button :url="$appointmentUrl">
Ver mi cita
</x-mail::button>

Si el botón no funciona, copia este enlace en el navegador: <a href="{{ $appointmentUrl }}">{{ $appointmentUrl }}</a>

Peluquería Jenver
</x-mail::message>
