{{-- PRF-130/PRF-149: every service of the appointment, by name. The
     duration (and the total, and the waits inside it, PRF-159) is an
     internal number for the salon: shown
     only when $showDuration is true (the two salon-facing emails), never
     in a customer-facing one, where this partial is included with no
     extra argument and $showDuration defaults to false.
     Markdown::withSecuredEncoding() (AppServiceProvider) already protects
     every {{ }} interpolated here, including a service's frozen name,
     from becoming a link.
     The hairdresser (PRF-167): the salon's emails ($showDuration) always
     say who, or that it is unassigned; the customer's only name the one
     she chose herself. --}}
@php($showDuration ??= false)
- **Servicios:** {{ $appointment->items->map(fn ($item) => $showDuration ? $item->service_name.' ('.\App\Models\Service::formatDurationWithWait($item->duration_minutes, \App\Booking\TimeProfile::fromServices([$item])->waitMinutes()).')' : $item->service_name)->implode(', ') }}
@if ($showDuration)
- **Peluquera:** {{ $appointment->hairdresser?->name ?? 'cualquiera (sin asignar)' }}
@elseif ($appointment->requestedHairdresser() !== null)
- **Peluquera:** {{ $appointment->requestedHairdresser()->name }}
@endif
@if ($showDuration)
- **Duración total:** {{ \App\Models\Service::formatDurationWithWait($appointment->durationMinutes(), $appointment->waitMinutes()) }}
@if ($appointment->waitsLabel() !== null)
- **Espera:** {{ $appointment->waitsLabel() }}
@endif
@endif
