@php
    $monthTitle = ucfirst($month->locale('es')->translatedFormat('F Y'));
    $leadingBlanks = $month->isoWeekday() - 1;
    // Weeks of 7 cells (review finding M3): grouping the grid into proper
    // ARIA rows needs the leading blanks and day numbers chunked together,
    // not rendered as one flat sequence.
    $calendarCells = array_merge(array_fill(0, $leadingBlanks, null), range(1, $month->daysInMonth));
    $calendarWeeks = array_chunk($calendarCells, 7);
@endphp
<div>
    <h2 class="font-serif text-2xl text-white mb-4">{{ __('reservas.steps.day') }}</h2>

    <div class="border border-[#2A2A2A] p-4 sm:p-6">
        <div class="flex items-center justify-between mb-4 text-sm">
            @if ($previousMonth)
                <a href="{{ route('reservas', ['servicio' => $service->id, 'mes' => $previousMonth->format('Y-m')]) }}" class="text-gray-300 hover:text-gold">{{ __('reservas.calendar.previous') }}</a>
            @else
                <span></span>
            @endif
            <p class="font-serif text-xl text-white" aria-live="polite">{{ $monthTitle }}</p>
            @if ($nextMonth)
                <a href="{{ route('reservas', ['servicio' => $service->id, 'mes' => $nextMonth->format('Y-m')]) }}" class="text-gray-300 hover:text-gold">{{ __('reservas.calendar.next') }}</a>
            @else
                <span></span>
            @endif
        </div>

        <div class="grid grid-cols-7 gap-1 text-center text-sm" role="grid" aria-label="{{ $monthTitle }}">
            {{-- The <div role="row" class="contents"> wrappers opt out of
                 CSS grid placement (display:contents) so grid-cols-7 still
                 applies to their children, not to the wrappers themselves
                 — otherwise each week would become a single grid column
                 (review finding M3). --}}
            <div role="row" class="contents">
                @foreach (__('reservas.calendar.weekdays') as $weekday)
                    <div class="py-1 text-gray-500 font-semibold" role="columnheader">{{ $weekday }}</div>
                @endforeach
            </div>

            @foreach ($calendarWeeks as $week)
                <div role="row" class="contents">
                    @foreach ($week as $number)
                        @if ($number === null)
                            <div role="presentation" aria-hidden="true"></div>
                        @else
                            @php
                                $date = $month->setDay($number);
                                $isAvailable = in_array($date->toDateString(), $availableDays, true);
                                $isSelected = $day !== null && $date->isSameDay($day);
                            @endphp
                            @if ($isAvailable)
                                <a href="{{ route('reservas', ['servicio' => $service->id, 'mes' => $month->format('Y-m'), 'fecha' => $date->toDateString()]) }}#horas"
                                   role="gridcell"
                                   class="min-h-11 flex items-center justify-center border {{ $isSelected ? 'bg-gold text-black border-gold font-semibold' : 'border-gold/40 text-white hover:bg-gold/20' }}"
                                   @if ($isSelected) aria-current="date" @endif>{{ $number }}</a>
                            @else
                                <span role="gridcell" class="min-h-11 flex items-center justify-center border border-transparent text-gray-600" aria-disabled="true">{{ $number }}</span>
                            @endif
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>

        <p class="text-xs text-gray-400 mt-4">{{ __('reservas.calendar.legend') }}</p>
        @if ($availableDays === [])
            <p class="text-gray-300 mt-2">{{ __('reservas.calendar.no_days') }}</p>
        @endif
    </div>
</div>
