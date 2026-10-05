{{-- Vista Semana (T030). Expects $days (list of ['date', 'appointments',
     'blocks', 'isClosed', 'hasPartialClosure', 'isToday']), $weekStart,
     $weekEnd, $day (the selected date), $volver and $weekdays (shared
     from index.blade.php). --}}
@php
    $weekdayShort = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
    $selected = collect($days)->first(fn ($d) => $d['date']->isSameDay($day));
@endphp

{{-- Mobile: a 7-day strip to pick the day, with its agenda below (PRF-101). --}}
<div class="md:hidden">
    <div class="grid grid-cols-7 gap-1 mb-4" role="grid" aria-label="Días de la semana">
        <div role="row" class="contents">
            @foreach ($days as $d)
                @php
                    $isSelected = $d['date']->isSameDay($day);
                    // Confirmed only (same rule as vista Mes): a cancelled
                    // appointment still shows in the day's agenda below, but
                    // must not read as an upcoming booking on the pill.
                    $count = $d['appointments']->filter->isConfirmed()->count();
                    $status = $d['isClosed']
                        ? ', cerrado'
                        : ($d['hasPartialClosure']
                            ? ', capacidad reducida'
                            : ($count > 0 ? ', '.$count.' '.($count === 1 ? 'cita' : 'citas') : ', sin citas'));
                @endphp
                <a href="{{ route('admin.agenda', ['vista' => 'semana', 'fecha' => $d['date']->toDateString()]) }}"
                   role="gridcell"
                   class="min-h-11 flex flex-col items-center justify-center border text-xs {{ $isSelected ? 'bg-gold text-black border-gold font-semibold' : ($d['isToday'] ? 'border-gold text-white' : 'border-[#2A2A2A] text-white') }} {{ $d['isClosed'] && ! $isSelected ? 'opacity-50' : '' }}"
                   {!! $isSelected ? 'aria-current="date"' : '' !!}
                   aria-label="{{ $weekdays[$d['date']->isoWeekday()] }} {{ $d['date']->format('d/m') }}{{ $status }}">
                    <span>{{ $weekdayShort[$d['date']->isoWeekday()] }}</span>
                    <span class="font-semibold">{{ $d['date']->day }}</span>
                    {{-- Today must not rely on the border color alone
                         (PRF-106): when it is not the selected day, a text
                         label makes it explicit for colorblind users and
                         screen readers alike. --}}
                    @if ($d['isToday'] && ! $isSelected)
                        <span class="text-[8px] leading-none text-gold">hoy</span>
                    @endif
                    {{-- A full closure is already covered by isClosed above
                         (opacity + "cerrado" in the label); a partial one
                         (capacity reduction) gets its own small label, same
                         information vista Día gives in its amber banner
                         (review finding H1). --}}
                    @if ($d['isClosed'])
                        <span class="text-[8px] leading-none">cerrado</span>
                    @elseif ($d['hasPartialClosure'])
                        <span class="text-[8px] leading-none text-amber-400">menos plazas</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    @if ($selected['isClosed'])
        <p class="mb-4 border border-[#2A2A2A] bg-[#111111] text-gray-400 px-4 py-2 text-sm">Cerrado.</p>
    @endif

    @include('admin.agenda._day', ['appointments' => $selected['appointments'], 'blocks' => $selected['blocks'], 'timeline' => $selected['timeline'], 'gridStart' => $gridStart, 'gridEnd' => $gridEnd, 'nowLineTop' => $selected['nowLineTop']])
</div>

{{-- Desktop: a 7-column overview of the week (PRF-100). Two ARIA rows (a
     header row of columnheaders and a content row of gridcells) instead of
     one row with the header baked into each content cell, so the grid role
     has the row/gridcell structure a screen reader expects (review finding
     M3) — the two <div role="row" class="contents"> wrappers opt out of
     CSS grid placement (display:contents) so grid-cols-7 still applies to
     their children, not to the wrappers themselves. --}}
<div class="hidden md:grid md:grid-cols-7 md:gap-px md:bg-[#2A2A2A] md:border md:border-[#2A2A2A]" role="grid" aria-label="Semana del {{ $weekStart->format('d/m') }} al {{ $weekEnd->format('d/m/Y') }}">
    <div role="row" class="contents">
        @foreach ($days as $d)
            @php
                // The week can cross into the next month (review finding
                // L3): show the day alone only while it stays in the
                // week's first month, "d/m" once it has crossed over.
                $crossesMonth = $d['date']->month !== $weekStart->month;
            @endphp
            <div class="bg-black p-2 text-xs text-gray-400 capitalize" role="columnheader">
                {{ $weekdays[$d['date']->isoWeekday()] }} {{ $crossesMonth ? $d['date']->format('d/m') : $d['date']->format('d') }}
                {{-- Today must not rely on the ring color alone (PRF-106). --}}
                @if ($d['isToday'])
                    <span class="text-gold font-semibold">· Hoy</span>
                @endif
            </div>
        @endforeach
    </div>
    <div role="row" class="contents">
        @foreach ($days as $d)
            <div class="bg-black p-2 min-h-32 {{ $d['isToday'] ? 'ring-1 ring-inset ring-gold' : '' }}" role="gridcell">
                @if ($d['isClosed'])
                    <p class="text-sm text-gray-500 mb-1">Cerrado</p>
                @elseif ($d['hasPartialClosure'])
                    <p class="text-sm text-amber-400 mb-1">Capacidad reducida</p>
                @endif
                @if ($d['appointments']->isEmpty())
                    @unless ($d['isClosed'] || $d['hasPartialClosure'])
                        <p class="text-sm text-gray-500">Sin citas</p>
                    @endunless
                @else
                    {{-- Each appointment links to editing it when that is
                         possible, or to its day otherwise (cancelled or
                         already started), with a 44px touch target and a
                         full aria-label (review finding N3). --}}
                    <ul class="space-y-1">
                        @foreach ($d['appointments'] as $appointment)
                            @php
                                $canEdit = $appointment->isConfirmed() && $appointment->starts_at->isFuture();
                                $href = $canEdit
                                    ? route('admin.appointments.edit', ['appointment' => $appointment, 'volver' => $volver])
                                    : route('admin.agenda', ['vista' => 'dia', 'fecha' => $d['date']->toDateString()]);
                                $statusLabel = $appointment->isConfirmed() ? '' : ', cancelada';
                            @endphp
                            <li>
                                <a href="{{ $href }}"
                                   class="min-h-11 flex items-center gap-1 px-1 -mx-1 text-sm {{ $appointment->isConfirmed() ? 'text-white hover:text-gold' : 'text-gray-500 line-through' }}"
                                   aria-label="{{ $appointment->starts_at->format('H:i') }} {{ $appointment->service_name }}, {{ $appointment->customer_name }}{{ $statusLabel }}">
                                    <span>{{ $appointment->starts_at->format('H:i') }} {{ $appointment->service_name }} · {{ $appointment->customer_name }}</span>
                                    {{-- Cancelled must not rely on the strike-through alone (review finding N3). --}}
                                    @unless ($appointment->isConfirmed())
                                        <span class="text-xs no-underline">(Cancelada)</span>
                                    @endunless
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</div>
