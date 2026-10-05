{{-- Vista Semana (T030). Expects $days (list of ['date', 'appointments',
     'blocks', 'isClosed', 'isToday']), $weekStart, $weekEnd, $day (the
     selected date) and $weekdays (shared from index.blade.php). --}}
@php
    $weekdayShort = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
    $selected = collect($days)->first(fn ($d) => $d['date']->isSameDay($day));
@endphp

{{-- Mobile: a 7-day strip to pick the day, with its agenda below (PRF-101). --}}
<div class="md:hidden">
    <div class="grid grid-cols-7 gap-1 mb-4" role="grid" aria-label="Días de la semana">
        @foreach ($days as $d)
            @php
                $isSelected = $d['date']->isSameDay($day);
                $count = $d['appointments']->count();
            @endphp
            <a href="{{ route('admin.agenda', ['vista' => 'semana', 'fecha' => $d['date']->toDateString()]) }}"
               class="min-h-11 flex flex-col items-center justify-center border text-xs {{ $isSelected ? 'bg-gold text-black border-gold font-semibold' : ($d['isToday'] ? 'border-gold text-white' : 'border-[#2A2A2A] text-white') }} {{ $d['isClosed'] && ! $isSelected ? 'opacity-50' : '' }}"
               {!! $isSelected ? 'aria-current="date"' : '' !!}
               aria-label="{{ $weekdays[$d['date']->isoWeekday()] }} {{ $d['date']->format('d/m') }}{{ $d['isClosed'] ? ', cerrado' : ($count > 0 ? ', '.$count.' '.($count === 1 ? 'cita' : 'citas') : ', sin citas') }}">
                <span>{{ $weekdayShort[$d['date']->isoWeekday()] }}</span>
                <span class="font-semibold">{{ $d['date']->day }}</span>
            </a>
        @endforeach
    </div>

    @if ($selected['isClosed'])
        <p class="mb-4 border border-[#2A2A2A] bg-[#111111] text-gray-400 px-4 py-2 text-sm">Cerrado.</p>
    @endif

    @include('admin.agenda._day', ['appointments' => $selected['appointments'], 'blocks' => $selected['blocks']])
</div>

{{-- Desktop: a 7-column overview of the week (PRF-100). --}}
<div class="hidden md:grid md:grid-cols-7 md:gap-px md:bg-[#2A2A2A] md:border md:border-[#2A2A2A]" role="grid" aria-label="Semana del {{ $weekStart->format('d/m') }} al {{ $weekEnd->format('d/m/Y') }}">
    @foreach ($days as $d)
        <div class="bg-black p-2 min-h-40 {{ $d['isToday'] ? 'ring-1 ring-inset ring-gold' : '' }}">
            <p class="text-xs text-gray-400 mb-2 capitalize">{{ $weekdays[$d['date']->isoWeekday()] }} {{ $d['date']->format('d') }}</p>
            @if ($d['isClosed'])
                <p class="text-xs text-gray-500">Cerrado</p>
            @elseif ($d['appointments']->isEmpty())
                <p class="text-xs text-gray-500">Sin citas</p>
            @else
                <ul class="space-y-1">
                    @foreach ($d['appointments'] as $appointment)
                        <li class="text-xs {{ $appointment->isConfirmed() ? 'text-white' : 'text-gray-500 line-through' }}">
                            {{ $appointment->starts_at->format('H:i') }} {{ $appointment->service_name }} · {{ $appointment->customer_name }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</div>
