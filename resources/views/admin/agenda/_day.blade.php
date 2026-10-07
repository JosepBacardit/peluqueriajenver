{{-- The Día agenda content: cierres of the day, the timeline grid
     (PRF-108 and on), then the appointment cards. Extracted from
     index.blade.php (T030) so vista Semana can reuse it unchanged for the
     mobile day strip's selected day. Expects $appointments, $blocks,
     $timeline, $gridStart, $gridEnd and $nowLineTop. --}}
@foreach ($blocks as $block)
    <p class="mb-4 border border-amber-500/40 bg-amber-500/10 text-amber-200 px-4 py-2 text-sm">
        {{ $block->isFullClosure() ? 'Cierre total' : 'Capacidad reducida en '.$block->capacity_reduction }}:
        {{ $block->starts_at->format('d/m H:i') }} → {{ $block->ends_at->format('d/m H:i') }}@if ($block->reason) · {{ $block->reason }}@endif
    </p>
@endforeach

@include('admin.agenda._timeline')

{{-- Review finding L4: the "Saltar a las citas" skip link above the grid
     targets this id, so a keyboard user can jump straight past the (now
     globally-chronological, but still long) grid tab sequence to the
     actual appointment cards. --}}
<div id="citas-del-dia">
@if ($appointments->isEmpty())
    <p class="text-gray-300">No hay citas este día.</p>
@else
    <ul class="space-y-3">
        @foreach ($appointments as $appointment)
        <li id="cita-{{ $appointment->id }}" class="border border-[#2A2A2A] p-4 scroll-mt-4 {{ $appointment->isConfirmed() ? 'bg-[#111111]' : 'opacity-60' }}">
            <div class="flex flex-wrap justify-between gap-4">
                <div class="space-y-1">
                    <p class="text-lg">
                        <span class="text-gold font-semibold">{{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }}</span>
                        · {{ $appointment->services_label }}
                        {{-- PRF-130: the total duration, spelled out next
                             to the summary — durationMinutes() is just
                             starts_at/ends_at, already loaded, so this
                             never needs items() (no N+1, same number of
                             queries as before this card existed). --}}
                        <span class="text-sm text-gray-400">({{ \App\Models\Service::formatDuration($appointment->durationMinutes()) }})</span>
                    </p>
                    <p>
                        {{ $appointment->customer_name }} · {{ $appointment->customer_phone }}
                        @if ($appointment->customer_email) · {{ $appointment->customer_email }}@endif
                    </p>
                    @if ($appointment->isConfirmed() && $appointment->customer_email && $appointment->customer_notified_at === null)
                        <p class="text-sm text-amber-300">Correo de confirmación no enviado: el sistema lo reintenta cada 10 minutos. Si sigue así, revisa el email o avisa al cliente por teléfono.</p>
                    @endif
                    @if ($appointment->notes)
                        <p class="text-sm text-gray-400">{{ $appointment->notes }}</p>
                    @endif
                    <p class="text-xs text-gray-400">Origen: {{ $appointment->source->label() }} · Estado: <span class="{{ $appointment->isConfirmed() ? 'text-green-300' : 'text-red-300' }}">{{ $appointment->status->label() }}</span></p>
                </div>
                {{-- Calling and WhatsApp are what the salon uses most, so they
                     are large buttons here instead of small text links in the
                     line above (review finding N1); kept even on a cancelled
                     appointment, since the salon may still need to reach the
                     customer. Editar/Cancelar stay appointment actions only. --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 w-full sm:w-auto">
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $appointment->customer_phone) }}" class="btn-outline text-sm" aria-label="Llamar a {{ $appointment->customer_name }}">Llamar</a>
                    <a href="{{ $appointment->customerWhatsappUrl() }}" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm" aria-label="Abrir WhatsApp con {{ $appointment->customer_name }}">WhatsApp</a>
                    @if ($appointment->isConfirmed())
                        @if ($appointment->starts_at->isFuture())
                            {{-- "volver" (review finding M1): so saving or
                                 cancelling returns to this same view/date,
                                 not always vista Día. --}}
                            <a href="{{ route('admin.appointments.edit', ['appointment' => $appointment, 'volver' => $volver]) }}" class="btn-outline text-sm" aria-label="Editar o mover la cita de {{ $appointment->customer_name }} a las {{ $appointment->starts_at->format('H:i') }}">Editar</a>
                        @endif
                        <form method="POST" action="{{ route('admin.appointments.cancel', $appointment) }}" onsubmit="return confirm('¿Cancelar la cita de {{ e($appointment->customer_name) }}? Su hora quedará libre.');">
                            @csrf
                            <input type="hidden" name="volver" value="{{ $volver }}">
                            <button type="submit" class="btn-danger-outline text-sm w-full">Cancelar cita</button>
                        </form>
                    @endif
                </div>
            </div>
        </li>
        @endforeach
    </ul>
@endif
</div>
