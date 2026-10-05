{{-- Vista Mes (T031). Expects $month, $occupancy (date => count),
     $openWeekdays, $fullClosures. --}}
@php
    $monthTitle = ucfirst($month->locale('es')->translatedFormat('F Y'));
    $leadingBlanks = $month->isoWeekday() - 1;
    $weekdayShort = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
    $today = \Carbon\CarbonImmutable::today();
    // Weeks of 7 cells (review finding M3): grouping the grid into proper
    // ARIA rows needs the leading blanks and day numbers chunked together,
    // not rendered as one flat sequence.
    $cells = array_merge(array_fill(0, $leadingBlanks, null), range(1, $month->daysInMonth));
    $weeks = array_chunk($cells, 7);
@endphp

<div class="border border-[#2A2A2A] p-4 sm:p-6">
    <div class="grid grid-cols-7 gap-1 text-center text-sm" role="grid" aria-label="{{ $monthTitle }}">
        {{-- The <div role="row" class="contents"> wrappers opt out of CSS
             grid placement (display:contents) so grid-cols-7 still applies
             to their children, not to the wrappers themselves — otherwise
             each week would become a single grid column. --}}
        <div role="row" class="contents">
            @foreach ($weekdayShort as $label)
                <div class="py-1 text-gray-500 font-semibold" role="columnheader">{{ $label }}</div>
            @endforeach
        </div>

        @foreach ($weeks as $week)
            <div role="row" class="contents">
                @foreach ($week as $number)
                    @if ($number === null)
                        <div role="presentation" aria-hidden="true"></div>
                    @else
                        @php
                            $date = $month->setDay($number);
                            $isToday = $date->isSameDay($today);
                            $isClosed = ! in_array($date->isoWeekday(), $openWeekdays, true)
                                || $fullClosures->contains(fn ($block) => $block->starts_at->lt($date->addDay()) && $block->ends_at->gt($date));
                            $count = (int) ($occupancy[$date->toDateString()] ?? 0);
                            $monthDay = $date->locale('es')->isoFormat('D [de] MMMM');
                        @endphp
                        <a href="{{ route('admin.agenda', ['vista' => 'dia', 'fecha' => $date->toDateString()]) }}"
                           role="gridcell"
                           class="min-h-11 flex flex-col items-center justify-center border {{ $isToday ? 'border-gold' : 'border-[#2A2A2A]' }} {{ $isClosed ? 'text-gray-500' : 'text-white hover:bg-gold/10' }}"
                           {!! $isToday ? 'aria-current="date"' : '' !!}
                           aria-label="{{ $isClosed ? "$monthDay, cerrado" : ($count > 0 ? "$monthDay, $count ".($count === 1 ? 'cita' : 'citas') : "$monthDay, sin citas") }}">
                            <span class="{{ $isToday ? 'font-semibold' : '' }}">{{ $number }}</span>
                            @if ($isClosed)
                                <span class="text-[10px] leading-none">Cerrado</span>
                            @elseif ($count > 0)
                                <span class="text-[10px] leading-none text-gold">{{ $count }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>

    <p class="text-xs text-gray-400 mt-4">Hoy tiene un borde dorado. El número bajo cada día es sus citas confirmadas. «Cerrado» son los días sin horario o con un cierre total.</p>
</div>
