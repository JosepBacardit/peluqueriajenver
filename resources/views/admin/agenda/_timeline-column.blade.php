{{-- One day's timeline column (PRF-108 to PRF-113): bands for
     cerrado/fuera-horario/cierre, and for each "open" piece, a CSS grid
     (one column per lane, one row per distinct boundary minute, sized in
     "fr" units so proportions never drift from integer rounding) placing
     every free/appointment/cierre-parcial segment by explicit grid-row/
     grid-column. $piece['segments'] is already in global chronological
     order (review finding L4) — DOM order here is the tab order, so a
     keyboard/screen-reader user reaches citas in the order they actually
     happen, not "Plaza 1" in full, then "Plaza 2" in full. Because of
     that, segments can no longer be wrapped in one contiguous
     role="group" per lane (a lane's segments are not contiguous in the
     DOM any more); each segment's own aria-label says its plaza instead
     (review finding N6).
     Expects $timeline (DayTimeline::build() result), $compact (bool:
     hides the service name in narrow contexts, PRF-111), $day, $volver,
     optionally $nowLineTop (int px, PRF-116 — omit or pass null when this
     column's day is not today or "ahora" is outside the grid) and
     optionally $ariaDateLabel (string, review finding M2: prefixes every
     aria-label in Semana's columns with the day, e.g. "miércoles 7", so a
     screen-reader user knows which day's plaza/cita/hueco they are on;
     omitted in vista Día, where there is only one day on screen).
     Also inherits $servicio (nullable Service, PRF-120: the selected
     service filter — AgendaController already flagged every segment
     that fits it with 'fits' => true) and $servicioQuery (array, the
     "servicio" query fragment, PRF-120: added to every "Nueva cita" link
     so the filter survives into the create form, preselected, T045) from
     the including view's own scope — @include shares it automatically,
     so neither _day.blade.php, _timeline.blade.php nor _week.blade.php
     need to repeat it explicitly. --}}
@php
    $bandLabels = ['cerrado' => 'Cerrado', 'fuera-horario' => 'Fuera de horario', 'cierre' => 'Cierre'];
    $datePrefix = ($ariaDateLabel ?? null) !== null ? $ariaDateLabel.', ' : '';
    $servicioQuery = $servicioQuery ?? [];
    $servicio = $servicio ?? null;
@endphp
<div class="relative flex-1 min-w-0">
    @foreach ($timeline['pieces'] as $piece)
        @if ($piece['kind'] === 'band')
            <div class="flex items-center justify-center text-center px-1 text-[10px] leading-tight text-amber-200 border-b border-[#2A2A2A]"
                 style="height: {{ $piece['height'] }}px; background-image: repeating-linear-gradient(45deg, rgba(217,180,80,.12) 0 6px, transparent 6px 12px);">
                {{ $bandLabels[$piece['bandType']] }}
            </div>
        @else
            {{-- Hour/half-hour lines across the whole lane width (review
                 finding N2), like Google Calendar: two stacked gradients,
                 the hour one listed first so it visually wins where both
                 coincide. background-position-y is offset by this piece's
                 own top modulo each period, so the lines land on the same
                 absolute minute marks across every piece, not restarting
                 at each piece's own top. --}}
            <div class="grid"
                 style="height: {{ $piece['height'] }}px;
                        grid-template-rows: {{ $piece['gridTemplateRows'] }};
                        grid-template-columns: repeat({{ $piece['maxLanes'] }}, 1fr);
                        background-image: linear-gradient(to bottom, rgba(255,255,255,.18) 0 1px, transparent 1px 100%), linear-gradient(to bottom, rgba(255,255,255,.08) 0 1px, transparent 1px 100%);
                        background-size: 100% {{ \App\Booking\DayTimeline::PX_PER_HOUR }}px, 100% {{ \App\Booking\DayTimeline::PX_PER_HOUR / 2 }}px;
                        background-position-y: -{{ $piece['top'] % \App\Booking\DayTimeline::PX_PER_HOUR }}px, -{{ $piece['top'] % (\App\Booking\DayTimeline::PX_PER_HOUR / 2) }}px;">
                @foreach ($piece['segments'] as $segment)
                    @php
                        $gridArea = 'grid-row: '.$segment['gridRowStart'].' / '.$segment['gridRowEnd'].'; grid-column: '.($segment['lane'] + 1).';';
                    @endphp
                    @if ($segment['type'] === 'appointment')
                        @php
                            $appointment = $segment['appointment'];
                        @endphp
                        {{-- A single truncated line, "HH:MM Clienta" (and the
                             service, if room): review finding N3. A short
                             block (e.g. 15 min, 22px) used to break its
                             second line in half; there is no faked minimum
                             height here (that would misrepresent the real
                             start time), so a very short appointment still
                             only shows this one line — its full detail stays
                             a tap away, on its card below. --}}
                        <a href="#cita-{{ $appointment->id }}"
                           class="flex items-center overflow-hidden px-1 text-[11px] leading-tight bg-[#1c1c1c] border {{ $segment['overCapacity'] ? 'border-amber-400' : 'border-gold/40' }} hover:border-gold"
                           style="{{ $gridArea }}"
                           title="{{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }} {{ $appointment->service_name }}, {{ $appointment->customer_name }}"
                           aria-label="{{ $datePrefix }}{{ $appointment->starts_at->format('H:i') }} {{ $appointment->service_name }}, {{ $appointment->customer_name }}{{ $segment['overCapacity'] ? ', sobre capacidad' : '' }}, plaza {{ $segment['lane'] + 1 }}">
                            <span class="block truncate w-full">
                                <span class="font-semibold text-gold">{{ $appointment->starts_at->format('H:i') }}</span>
                                {{ $appointment->customer_name }}@unless ($compact) · {{ $appointment->service_name }}@endunless
                            </span>
                        </a>
                    @elseif ($segment['type'] === 'cierre-parcial')
                        {{-- Always carries an aria-label (review finding L3):
                             a short partial-closure band (under ~20px) used
                             to show no text and no accessible name at all,
                             silent to anyone not seeing its stripe pattern. --}}
                        <div class="flex items-center justify-center text-[9px] leading-none text-amber-200"
                             style="{{ $gridArea }}; background-image: repeating-linear-gradient(45deg, rgba(217,180,80,.12) 0 6px, transparent 6px 12px);"
                             aria-label="{{ $datePrefix }}Cierre parcial, plaza {{ $segment['lane'] + 1 }}"
                             title="Cierre parcial">
                            @if ($segment['height'] >= 20)
                                Cierre
                            @endif
                        </div>
                    @elseif ($segment['tappable'])
                        @php
                            $slotTime = sprintf('%02d:%02d', intdiv($segment['start'], 60), $segment['start'] % 60);
                            $fits = $segment['fits'] ?? false;
                        @endphp
                        {{-- One link per free half hour of this lane (review
                             finding N1), each with its own exact time —
                             never one giant link for the whole free run.
                             When a service is selected and it fits starting
                             here (PRF-123/124), the link gets a visibly
                             different border/background — never only a
                             color swap — plus the word "Cabe" when there is
                             room ($compact false, vista Día) and always an
                             aria-label saying so, for Semana's narrow
                             columns and for anyone not seeing the border. --}}
                        <a href="{{ route('admin.appointments.create', ['fecha' => $day->toDateString(), 'hora' => $slotTime, 'volver' => $volver, ...$servicioQuery]) }}"
                           class="flex items-center justify-center text-[9px] leading-none text-gold font-semibold {{ $fits ? 'border-2 border-gold bg-gold/15' : 'hover:bg-gold/10' }}"
                           style="{{ $gridArea }}"
                           aria-label="{{ $datePrefix }}Hueco libre a las {{ $slotTime }}, plaza {{ $segment['lane'] + 1 }}{{ $fits ? ', cabe '.$servicio->name : '' }}">
                            @if ($fits && ! $compact)
                                Cabe
                            @endif
                        </a>
                    @else
                        <div style="{{ $gridArea }}" aria-hidden="true"></div>
                    @endif
                @endforeach
            </div>
        @endif
    @endforeach
    @if (($nowLineTop ?? null) !== null)
        @include('admin.agenda._timeline-now-line')
    @endif
</div>
