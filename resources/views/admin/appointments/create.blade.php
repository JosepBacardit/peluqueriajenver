@extends('layouts.admin')

@section('title', 'Nueva cita')

@section('content')
<h1 class="font-serif text-3xl text-white mb-2">Nueva cita</h1>
<p class="text-sm text-gray-400 mb-6">Para las citas que llegan por teléfono o WhatsApp. Se comprueban el horario y la capacidad, pero no la antelación mínima ni el intervalo de la web.</p>

@php
    // py-3 (not py-2): every field, including the date/time pickers, meets
    // the 44px touch target (review finding N3).
    $inputClass = 'w-full bg-black border border-[#2A2A2A] px-3 py-3 focus:border-gold focus:outline-none';
    // aria-invalid and aria-describedby linking a field to its error.
    $fieldAria = fn (string $field): string => $errors->has($field) ? 'aria-invalid="true" aria-describedby="'.$field.'-error"' : '';
@endphp

@if ($services->isEmpty())
    <p class="text-gray-300">No hay servicios activos. <a href="{{ route('admin.services.create') }}" class="text-gold underline">Crea uno</a> antes de añadir citas.</p>
@else
<form method="POST" action="{{ route('admin.appointments.store') }}" class="space-y-5 max-w-2xl">
    @csrf
    {{-- Review finding M1: carried through to store() so it redirects back
         to the view/date the salon was on, not always vista Día. --}}
    @if ($volver)
        <input type="hidden" name="volver" value="{{ old('volver', $volver) }}">
    @endif

    <div>
        <label for="service_id" class="block text-sm mb-1">Servicio</label>
        <select id="service_id" name="service_id" required class="{{ $inputClass }}" {!! $fieldAria('service_id') !!}>
            @foreach ($services as $service)
                <option value="{{ $service->id }}" @selected((int) old('service_id') === $service->id)>{{ $service->name }} ({{ $service->duration_label }})</option>
            @endforeach
        </select>
        @error('service_id') <p id="service_id-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="date" class="block text-sm mb-1">Fecha</label>
            <input id="date" name="date" type="date" required value="{{ old('date', $day->toDateString()) }}" class="{{ $inputClass }}" {!! $fieldAria('date') !!}>
            @error('date') <p id="date-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="time" class="block text-sm mb-1">Hora</label>
            <input id="time" name="time" type="time" step="300" required value="{{ old('time') }}" class="{{ $inputClass }}" {!! $fieldAria('time') !!}>
            @error('time') <p id="time-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="customer_name" class="block text-sm mb-1">Nombre</label>
            <input id="customer_name" name="customer_name" type="text" maxlength="100" required value="{{ old('customer_name') }}" class="{{ $inputClass }}" {!! $fieldAria('customer_name') !!}>
            @error('customer_name') <p id="customer_name-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="customer_phone" class="block text-sm mb-1">Teléfono</label>
            <input id="customer_phone" name="customer_phone" type="tel" maxlength="20" required value="{{ old('customer_phone') }}" class="{{ $inputClass }}" {!! $fieldAria('customer_phone') !!}>
            @error('customer_phone') <p id="customer_phone-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="customer_email" class="block text-sm mb-1">Email (opcional: si lo pones, recibirá la confirmación)</label>
        <input id="customer_email" name="customer_email" type="email" maxlength="150" value="{{ old('customer_email') }}" class="{{ $inputClass }}" {!! $fieldAria('customer_email') !!}>
        @error('customer_email') <p id="customer_email-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="notes" class="block text-sm mb-1">Observaciones</label>
        <textarea id="notes" name="notes" maxlength="500" rows="3" class="{{ $inputClass }}" {!! $fieldAria('notes') !!}>{{ old('notes') }}</textarea>
        @error('notes') <p id="notes-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    @php
        // $volver is already validated ("vista:fecha" or null) by the
        // controller, so splitting it here is safe (review finding M1).
        $volverParts = $volver ? explode(':', $volver, 2) : null;
        $backRoute = $volverParts ? ['vista' => $volverParts[0], 'fecha' => $volverParts[1]] : ['fecha' => $day->toDateString()];
    @endphp
    <div class="flex gap-4 items-center">
        <button type="submit" class="btn-gold">Guardar cita</button>
        <a href="{{ route('admin.agenda', $backRoute) }}" class="text-gray-300 hover:text-gold">Volver a la agenda</a>
    </div>
</form>
@endif
@endsection
