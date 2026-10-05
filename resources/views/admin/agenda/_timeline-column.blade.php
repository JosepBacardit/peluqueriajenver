{{-- One day's timeline column (PRF-108 to PRF-113): bands for
     cerrado/fuera-horario/cierre, and for each "open" piece, one flex
     column per lane stacking its free/appointment/cierre-parcial
     segments in normal document flow — every segment's height is exact
     (DayTimeline::pxFromMinutes), so the lanes stay aligned to the shared
     hour axis without any absolute positioning.
     Expects $timeline (DayTimeline::build() result), $compact (bool:
     hides the service name in narrow contexts, PRF-111) and, optionally,
     $nowLineTop (int px, PRF-116 — omit or pass null when this column's
     day is not today or "ahora" is outside the grid). --}}
@php
    $bandLabels = ['cerrado' => 'Cerrado', 'fuera-horario' => 'Fuera de horario', 'cierre' => 'Cierre'];
@endphp
<div class="relative flex-1 min-w-0">
    @foreach ($timeline['pieces'] as $piece)
        @if ($piece['kind'] === 'band')
            <div class="flex items-center justify-center text-center px-1 text-[10px] leading-tight text-amber-200 border-b border-[#2A2A2A]"
                 style="height: {{ $piece['height'] }}px; background-image: repeating-linear-gradient(45deg, rgba(217,180,80,.12) 0 6px, transparent 6px 12px);">
                {{ $bandLabels[$piece['bandType']] }}
            </div>
        @else
            <div class="flex" style="height: {{ $piece['height'] }}px">
                @foreach ($piece['laneSegments'] as $lane => $segments)
                    {{-- A screen reader reads each lane's segments together
                         (PRF-119): "role=group" plus a label says which
                         plaza they belong to, since lanes never mean a
                         particular hairdresser — only capacity. --}}
                    <div class="flex-1 min-w-0 flex flex-col border-l border-[#1A1A1A] first:border-l-0" role="group" aria-label="Plaza {{ $lane + 1 }}">
                        @foreach ($segments as $segment)
                            @if ($segment['type'] === 'appointment')
                                @php
                                    $appointment = $segment['appointment'];
                                @endphp
                                <a href="#cita-{{ $appointment->id }}"
                                   class="block overflow-hidden px-1 py-0.5 text-[11px] leading-tight bg-[#1c1c1c] border {{ $segment['overCapacity'] ? 'border-amber-400' : 'border-gold/40' }} hover:border-gold"
                                   style="height: {{ $segment['height'] }}px"
                                   title="{{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }} {{ $appointment->service_name }}, {{ $appointment->customer_name }}"
                                   aria-label="{{ $appointment->starts_at->format('H:i') }} {{ $appointment->service_name }}, {{ $appointment->customer_name }}{{ $segment['overCapacity'] ? ', sobre capacidad' : '' }}">
                                    <span class="block font-semibold text-gold truncate">{{ $appointment->starts_at->format('H:i') }}</span>
                                    <span class="block truncate">{{ $appointment->customer_name }}@unless ($compact) · {{ $appointment->service_name }}@endunless</span>
                                    @if ($segment['overCapacity'])
                                        <span class="block text-[9px] text-amber-300 truncate">Sobre capacidad</span>
                                    @endif
                                </a>
                            @elseif ($segment['type'] === 'cierre-parcial')
                                <div class="flex items-center justify-center text-[9px] leading-none text-amber-200"
                                     style="height: {{ $segment['height'] }}px; background-image: repeating-linear-gradient(45deg, rgba(217,180,80,.12) 0 6px, transparent 6px 12px);">
                                    @if ($segment['height'] >= 20)
                                        Cierre
                                    @endif
                                </div>
                            @elseif ($segment['tappable'])
                                @php
                                    $slotTime = sprintf('%02d:%02d', intdiv($segment['startMinute'], 60), $segment['startMinute'] % 60);
                                @endphp
                                {{-- Free slot of 30 min or more (PRF-114): tap to create a
                                     booking at this exact time. Shorter gaps are not their
                                     own target — DayTimeline already marks them not
                                     tappable (not big enough for 44px). --}}
                                <a href="{{ route('admin.appointments.create', ['fecha' => $day->toDateString(), 'hora' => $slotTime, 'volver' => $volver]) }}"
                                   class="block hover:bg-gold/10"
                                   style="height: {{ $segment['height'] }}px"
                                   aria-label="Hueco libre a las {{ $slotTime }}, plaza {{ $lane + 1 }}"></a>
                            @else
                                <div style="height: {{ $segment['height'] }}px"></div>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach
    @if (($nowLineTop ?? null) !== null)
        @include('admin.agenda._timeline-now-line')
    @endif
</div>
