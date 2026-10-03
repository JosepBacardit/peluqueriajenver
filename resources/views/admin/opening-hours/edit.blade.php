@extends('layouts.admin')

@section('title', 'Horario')

@section('content')
<h1 class="font-serif text-3xl text-white mb-2">Horario semanal</h1>
<p class="text-sm text-gray-400 mb-6">Hasta dos tramos por día (por ejemplo, mañana y tarde). Deja un día vacío para cerrarlo. Los festivos y las vacaciones se añaden en «Cierres».</p>

<form method="POST" action="{{ route('admin.opening-hours.update') }}" class="space-y-4 max-w-3xl">
    @csrf
    @method('PUT')

    @foreach ($days as $weekday => $day)
        <fieldset class="border border-[#2A2A2A] p-4">
            <legend class="px-2 text-gold">{{ $day['name'] }}</legend>
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach ([0, 1] as $index)
                    @php
                        $opens = old("days.$weekday.$index.opens", $day['ranges'][$index]['opens'] ?? '');
                        $closes = old("days.$weekday.$index.closes", $day['ranges'][$index]['closes'] ?? '');
                    @endphp
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-gray-400 w-14">{{ $index === 0 ? 'Tramo 1' : 'Tramo 2' }}</span>
                        <input type="time" step="300" name="days[{{ $weekday }}][{{ $index }}][opens]" value="{{ $opens }}" aria-label="{{ $day['name'] }}, tramo {{ $index + 1 }}, inicio" class="bg-black border border-[#2A2A2A] px-2 py-1">
                        <span>–</span>
                        <input type="time" step="300" name="days[{{ $weekday }}][{{ $index }}][closes]" value="{{ $closes }}" aria-label="{{ $day['name'] }}, tramo {{ $index + 1 }}, fin" class="bg-black border border-[#2A2A2A] px-2 py-1">
                    </div>
                @endforeach
            </div>
            @foreach ($errors->get("days.$weekday*") as $messages)
                @foreach ((array) $messages as $message)
                    <p class="text-red-400 text-sm mt-2">{{ $message }}</p>
                @endforeach
            @endforeach
        </fieldset>
    @endforeach

    <button type="submit" class="btn-gold">Guardar horario</button>
</form>
@endsection
