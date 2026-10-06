<x-mail::message>
# Hemos cambiado tu cita

Hola, {{ $appointment->customer_name }}:

El salón ha cambiado tu cita. Estos son los nuevos datos:

- **Servicio:** {{ $appointment->services_label }}
- **Día:** {{ ucfirst($appointment->dayLabel()) }}
- **Hora:** {{ $appointment->starts_at->format('H:i') }}
- **Dónde:** C/ Lleida, 21 · 08110 Montcada i Reixac
- **Teléfono:** 633 912 050

Si esta hora no te va bien, llámanos o cancela la cita desde tu enlace de siempre:

<x-mail::button :url="$appointmentUrl">
Ver mi cita
</x-mail::button>

Si el botón no funciona, copia este enlace en el navegador: <a href="{{ $appointmentUrl }}">{{ $appointmentUrl }}</a>

Peluquería Jenver
</x-mail::message>
