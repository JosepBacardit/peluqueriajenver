{{-- One day's timeline column (PRF-108 to PRF-113): bands for
     cerrado/fuera-horario/cierre, and for each "open" piece, one flex
     column per lane stacking its free/appointment/cierre-parcial
     segments in normal document flow — every segment's height is exact
     (DayTimeline::pxFromMinutes), so the lanes stay aligned to the shared
     hour axis without any absolute positioning.
     Expects $timeline (DayTimeline::build() result) and $compact (bool:
     hides the service name in narrow contexts, PRF-111). --}}
@php
    $bandLabels = ['cerrado' => 'Cerrado', 'fuera-horario' => 'Fuera de horario', 'cierre' => 'Cierre'];
@endphp
<div class="flex-1 min-w-0">
    @foreach ($timeline['pieces'] as $piece)
        @if ($piece['kind'] === 'band')
            <div class="flex items-center justify-center text-center px-1 text-[10px] leading-tight text-amber-200 border-b border-[#2A2A2A]"
                 style="height: {{ $piece['height'] }}px; background-image: repeating-linear-gradient(45deg, rgba(217,180,80,.12) 0 6px, transparent 6px 12px);">
                {{ $bandLabels[$piece['bandType']] }}
            </div>
        @else
            <div class="flex" style="height: {{ $piece['height'] }}px">
                @foreach ($piece['laneSegments'] as $lane => $segments)
                    <div class="flex-1 min-w-0 flex flex-col border-l border-[#1A1A1A] first:border-l-0">
                        @foreach ($segments as $segment)
                            @if ($segment['type'] === 'appointment')
                                @php($appointment = $segment['appointment'])
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
                            @else
                                {{-- free: empty for now, T037 makes it a tappable link to "Nueva cita". --}}
                                <div style="height: {{ $segment['height'] }}px"></div>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach
</div>
