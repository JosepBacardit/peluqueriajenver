{{-- Vista Mes (T031). Expects $month, $occupancy (date => count),
     $openWeekdays, $fullClosures. --}}
@php
    $monthTitle = ucfirst($month->locale('es')->translatedFormat('F Y'));
    $leadingBlanks = $month->isoWeekday() - 1;
    $weekdayShort = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
    $today = \Carbon\CarbonImmutable::today();
@endphp

<div class="border border-[#2A2A2A] p-4 sm:p-6">
    <div class="grid grid-cols-7 gap-1 text-center text-sm" role="grid" aria-label="{{ $monthTitle }}">
        @foreach ($weekdayShort as $label)
            <div class="py-1 text-gray-500 font-semibold" role="columnheader">{{ $label }}</div>
        @endforeach

        @for ($blank = 0; $blank < $leadingBlanks; $blank++)
            <div aria-hidden="true"></div>
        @endfor

        @for ($number = 1; $number <= $month->daysInMonth; $number++)
            @php
                $date = $month->setDay($number);
                $isToday = $date->isSameDay($today);
                $isClosed = ! in_array($date->isoWeekday(), $openWeekdays, true)
                    || $fullClosures->contains(fn ($block) => $block->starts_at->lt($date->addDay()) && $block->ends_at->gt($date));
                $count = (int) ($occupancy[$date->toDateString()] ?? 0);
                $monthDay = $date->locale('es')->isoFormat('D [de] MMMM');
            @endphp
            <a href="{{ route('admin.agenda', ['vista' => 'dia', 'fecha' => $date->toDateString()]) }}"
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
        @endfor
    </div>

    <p class="text-xs text-gray-400 mt-4">Hoy tiene un borde dorado. El número bajo cada día es sus citas confirmadas. «Cerrado» son los días sin horario o con un cierre total.</p>
</div>
