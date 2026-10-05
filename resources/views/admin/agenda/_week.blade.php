{{-- Vista Semana (T030, hourly grid since T039). Expects $days (list of
     ['date', 'appointments', 'blocks', 'isClosed', 'hasPartialClosure',
     'isToday', 'timeline', 'nowLineTop']), $weekStart, $weekEnd, $day (the
     selected date), $gridStart, $gridEnd, $volver and $weekdays (shared
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

{{-- Desktop: the same hourly timeline grid as Día, repeated in 7 columns
     (PRF-100, PRF-118), with the hour axis shared once on the left. This
     replaces the earlier list-based 7-column overview entirely. --}}
<div class="hidden md:block border border-[#2A2A2A] overflow-hidden" aria-label="Semana del {{ $weekStart->format('d/m') }} al {{ $weekEnd->format('d/m/Y') }}">
    {{-- Header row: a spacer matching the hour axis width, then the 7 day
         headers — the one part of this view that is genuinely tabular, so
         it keeps the role="row"/"columnheader" pair (review finding M3's
         pattern). --}}
    <div class="flex border-b border-[#2A2A2A]" role="row">
        <div class="w-11 shrink-0" aria-hidden="true"></div>
        <div class="flex-1 flex">
            @foreach ($days as $d)
                @php
                    // The week can cross into the next month (review
                    // finding L3): show the day alone only while it stays
                    // in the week's first month, "d/m" once crossed over.
                    $crossesMonth = $d['date']->month !== $weekStart->month;
                @endphp
                <div class="flex-1 min-w-0 bg-black p-1 text-[10px] leading-tight text-gray-400 capitalize border-l border-[#1A1A1A] first:border-l-0" role="columnheader">
                    {{ $weekdays[$d['date']->isoWeekday()] }} {{ $crossesMonth ? $d['date']->format('d/m') : $d['date']->format('d') }}
                    {{-- Today must not rely on the border color alone (PRF-106). --}}
                    @if ($d['isToday'])
                        <span class="block text-gold font-semibold">Hoy</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Hour axis + 7 day columns, scrolled together to "ahora" on load
         (the script lives once, in _timeline.blade.php's pattern — here
         inlined since Semana has its own scroll container id). --}}
    <div id="week-timeline-scroll" class="flex overflow-y-auto" style="max-height: 70vh">
        @include('admin.agenda._timeline-hour-axis')
        @foreach ($days as $d)
            {{-- "day" and "volver" are overridden per column here: each
                 column's free slots must create on (and return to) that
                 column's own date, not the mobile strip's selected $day. --}}
            @include('admin.agenda._timeline-column', ['timeline' => $d['timeline'], 'compact' => true, 'nowLineTop' => $d['nowLineTop'], 'day' => $d['date'], 'volver' => 'semana:'.$d['date']->toDateString()])
        @endforeach
    </div>
</div>

@php($weekNowLineTop = collect($days)->first(fn ($d) => $d['nowLineTop'] !== null)['nowLineTop'] ?? null)
@if ($weekNowLineTop !== null)
    <script>
        (function () {
            var container = document.getElementById('week-timeline-scroll');
            if (container) {
                container.scrollTop = Math.max(0, {{ $weekNowLineTop }} - 100);
            }
        })();
    </script>
@endif
