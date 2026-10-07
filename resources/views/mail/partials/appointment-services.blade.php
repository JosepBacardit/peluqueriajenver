{{-- PRF-130: every service of the appointment, each with its own
     duration, plus the total — with just one service this still reads
     fine ("Servicios: Corte (30 min)" · "Duración total: 30 min"), no
     separate single-service wording needed. Markdown::withSecuredEncoding()
     (AppServiceProvider) already protects every {{ }} interpolated here,
     including a service's frozen name, from becoming a link. --}}
- **Servicios:** {{ $appointment->items->map(fn ($item) => $item->service_name.' ('.\App\Models\Service::formatDuration($item->duration_minutes).')')->implode(', ') }}
- **Duración total:** {{ \App\Models\Service::formatDuration($appointment->durationMinutes()) }}
