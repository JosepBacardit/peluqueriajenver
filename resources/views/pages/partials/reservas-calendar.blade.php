@php
    $monthTitle = ucfirst($month->locale('es')->translatedFormat('F Y'));
    $leadingBlanks = $month->isoWeekday() - 1;
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
            @foreach (__('reservas.calendar.weekdays') as $weekday)
                <div class="py-1 text-gray-500 font-semibold" role="columnheader">{{ $weekday }}</div>
            @endforeach

            @for ($blank = 0; $blank < $leadingBlanks; $blank++)
                <div aria-hidden="true"></div>
            @endfor

            @for ($number = 1; $number <= $month->daysInMonth; $number++)
                @php
                    $date = $month->setDay($number);
                    $isAvailable = in_array($date->toDateString(), $availableDays, true);
                    $isSelected = $day !== null && $date->isSameDay($day);
                @endphp
                @if ($isAvailable)
                    <a href="{{ route('reservas', ['servicio' => $service->id, 'mes' => $month->format('Y-m'), 'fecha' => $date->toDateString()]) }}#horas"
                       class="py-2 border {{ $isSelected ? 'bg-gold text-black border-gold font-semibold' : 'border-gold/40 text-white hover:bg-gold/20' }}"
                       @if ($isSelected) aria-current="date" @endif>{{ $number }}</a>
                @else
                    <span class="py-2 border border-transparent text-gray-600" aria-disabled="true">{{ $number }}</span>
                @endif
            @endfor
        </div>

        <p class="text-xs text-gray-400 mt-4">{{ __('reservas.calendar.legend') }}</p>
        @if ($availableDays === [])
            <p class="text-gray-300 mt-2">{{ __('reservas.calendar.no_days') }}</p>
        @endif
    </div>
</div>
