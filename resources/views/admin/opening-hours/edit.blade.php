@extends('layouts.admin')

@section('title', 'Horario')

@section('content')
<h1 class="font-serif text-3xl text-white mb-2">Horario semanal</h1>
<p class="text-sm text-gray-400 mb-6">Hasta dos tramos por día (por ejemplo, mañana y tarde). Marca «Cerrado» para no abrir ese día. Los festivos y las vacaciones se añaden en «Cierres».</p>

<form method="POST" action="{{ route('admin.opening-hours.update') }}" class="space-y-4 max-w-3xl">
    @csrf
    @method('PUT')

    @foreach ($days as $weekday => $day)
        @php
            $closed = old("days.$weekday.closed", $day['closed']);
            $errorId = $errors->has("days.$weekday") ? "dia-{$weekday}-error" : null;
        @endphp
        <fieldset class="border border-[#2A2A2A] p-4">
            <legend class="px-2 text-gold">{{ $day['name'] }}</legend>

            <label class="flex items-center gap-2 text-sm text-gray-300 mb-4 min-h-11">
                <input
                    type="checkbox"
                    name="days[{{ $weekday }}][closed]"
                    value="1"
                    data-closed-toggle
                    data-day="{{ $weekday }}"
                    @checked($closed)
                    class="w-5 h-5 shrink-0">
                Cerrado
            </label>

            <div class="grid sm:grid-cols-2 gap-4" data-ranges="{{ $weekday }}">
                @foreach ([0, 1] as $index)
                    @php
                        $range = $day['ranges'][$index] ?? [];
                        $opensHour = old("days.$weekday.$index.opens_hour", $range['opens_hour'] ?? '');
                        $opensMinute = old("days.$weekday.$index.opens_minute", $range['opens_minute'] ?? '00');
                        $closesHour = old("days.$weekday.$index.closes_hour", $range['closes_hour'] ?? '');
                        $closesMinute = old("days.$weekday.$index.closes_minute", $range['closes_minute'] ?? '00');
                    @endphp
                    {{-- Two <select>s per time (hour, minutes), not <input
                         type="time">: on the iPhone that control's wheel
                         has no way to clear itself, so removing a range or
                         closing a day could be impossible from it
                         (PRF-133). An hour of "—" removes the range. --}}
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-gray-400 w-14 shrink-0">{{ $index === 0 ? 'Tramo 1' : 'Tramo 2' }}</span>

                        <select
                            name="days[{{ $weekday }}][{{ $index }}][opens_hour]"
                            aria-label="{{ $day['name'] }}, tramo {{ $index + 1 }}, hora de inicio"
                            @if ($errorId) aria-describedby="{{ $errorId }}" @endif
                            {{ $closed ? 'disabled' : '' }}
                            class="bg-black border border-[#2A2A2A] px-1 py-3 min-w-0 min-h-11 w-14">
                            <option value="" @selected($opensHour === '')>—</option>
                            @foreach (\App\Http\Requests\Admin\OpeningHoursRequest::HOURS as $hour)
                                <option value="{{ $hour }}" @selected($opensHour === $hour)>{{ $hour }}</option>
                            @endforeach
                        </select>
                        <select
                            name="days[{{ $weekday }}][{{ $index }}][opens_minute]"
                            aria-label="{{ $day['name'] }}, tramo {{ $index + 1 }}, minutos de inicio"
                            @if ($errorId) aria-describedby="{{ $errorId }}" @endif
                            {{ $closed ? 'disabled' : '' }}
                            class="bg-black border border-[#2A2A2A] px-1 py-3 min-w-0 min-h-11 w-12">
                            @foreach (\App\Http\Requests\Admin\OpeningHoursRequest::MINUTES as $minute)
                                <option value="{{ $minute }}" @selected($opensMinute === $minute)>{{ $minute }}</option>
                            @endforeach
                        </select>

                        <span class="shrink-0">–</span>

                        <select
                            name="days[{{ $weekday }}][{{ $index }}][closes_hour]"
                            aria-label="{{ $day['name'] }}, tramo {{ $index + 1 }}, hora de fin"
                            @if ($errorId) aria-describedby="{{ $errorId }}" @endif
                            {{ $closed ? 'disabled' : '' }}
                            class="bg-black border border-[#2A2A2A] px-1 py-3 min-w-0 min-h-11 w-14">
                            <option value="" @selected($closesHour === '')>—</option>
                            @foreach (\App\Http\Requests\Admin\OpeningHoursRequest::HOURS as $hour)
                                <option value="{{ $hour }}" @selected($closesHour === $hour)>{{ $hour }}</option>
                            @endforeach
                        </select>
                        <select
                            name="days[{{ $weekday }}][{{ $index }}][closes_minute]"
                            aria-label="{{ $day['name'] }}, tramo {{ $index + 1 }}, minutos de fin"
                            @if ($errorId) aria-describedby="{{ $errorId }}" @endif
                            {{ $closed ? 'disabled' : '' }}
                            class="bg-black border border-[#2A2A2A] px-1 py-3 min-w-0 min-h-11 w-12">
                            @foreach (\App\Http\Requests\Admin\OpeningHoursRequest::MINUTES as $minute)
                                <option value="{{ $minute }}" @selected($closesMinute === $minute)>{{ $minute }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            @if ($errorId)
                <p id="{{ $errorId }}" class="text-red-400 text-sm mt-2" role="alert">{{ $errors->first("days.$weekday") }}</p>
            @endif
        </fieldset>
    @endforeach

    <button type="submit" class="btn-gold">Guardar horario</button>
</form>

{{-- Purely a visual nicety: when "Cerrado" is checked, the day's selects
     are disabled so they cannot be interacted with. It never substitutes
     for the server's own rule (OpeningHoursRequest ignores a closed day's
     ranges unconditionally), since a disabled field is simply never sent
     and a tampered request could re-enable one. --}}
<script>
document.querySelectorAll('[data-closed-toggle]').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
        var ranges = document.querySelector('[data-ranges="' + checkbox.dataset.day + '"]');
        ranges.querySelectorAll('select').forEach(function (select) {
            select.disabled = checkbox.checked;
        });
    });
});
</script>
@endsection
