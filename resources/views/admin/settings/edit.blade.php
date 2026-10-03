@extends('layouts.admin')

@section('title', 'Ajustes')

@section('content')
<h1 class="font-serif text-3xl text-white mb-6">Ajustes de la reserva</h1>

@php($inputClass = 'w-full bg-black border border-[#2A2A2A] px-3 py-2 focus:border-gold focus:outline-none')

<form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5 max-w-xl">
    @csrf
    @method('PUT')

    <div>
        <label for="capacity" class="block text-sm mb-1">Capacidad (citas a la vez, de 1 a 10)</label>
        <input id="capacity" name="capacity" type="number" min="1" max="10" required value="{{ old('capacity', $settings->capacity) }}" class="{{ $inputClass }}">
        <p class="text-xs text-gray-400 mt-1">Normalmente, el número de peluqueras que atienden a la vez.</p>
        @error('capacity') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="slot_interval_minutes" class="block text-sm mb-1">Intervalo entre horas ofrecidas</label>
        <select id="slot_interval_minutes" name="slot_interval_minutes" class="{{ $inputClass }}">
            @foreach ($intervals as $interval)
                <option value="{{ $interval }}" @selected((int) old('slot_interval_minutes', $settings->slot_interval_minutes) === $interval)>Cada {{ $interval }} minutos</option>
            @endforeach
        </select>
        @error('slot_interval_minutes') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="min_notice_minutes" class="block text-sm mb-1">Antelación mínima (minutos, hasta 10.080)</label>
        <input id="min_notice_minutes" name="min_notice_minutes" type="number" min="0" max="10080" required value="{{ old('min_notice_minutes', $settings->min_notice_minutes) }}" class="{{ $inputClass }}">
        @error('min_notice_minutes') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="max_advance_days" class="block text-sm mb-1">Antelación máxima (días, de 1 a 365)</label>
        <input id="max_advance_days" name="max_advance_days" type="number" min="1" max="365" required value="{{ old('max_advance_days', $settings->max_advance_days) }}" class="{{ $inputClass }}">
        @error('max_advance_days') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="cancellation_limit_hours" class="block text-sm mb-1">Plazo para cancelar online (horas antes de la cita, hasta 168)</label>
        <input id="cancellation_limit_hours" name="cancellation_limit_hours" type="number" min="0" max="168" required value="{{ old('cancellation_limit_hours', $settings->cancellation_limit_hours) }}" class="{{ $inputClass }}">
        @error('cancellation_limit_hours') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <button type="submit" class="btn-gold">Guardar ajustes</button>
</form>
@endsection
