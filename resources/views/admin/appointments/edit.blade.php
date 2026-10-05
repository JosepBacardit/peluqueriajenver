@extends('layouts.admin')

@section('title', 'Editar cita')

@section('content')
<h1 class="font-serif text-3xl text-white mb-2">Editar cita</h1>
<p class="text-sm text-gray-400 mb-6">Puedes cambiar el día, la hora, el servicio y los datos de la clienta. Se comprueban el horario y la capacidad; si la nueva hora está llena o fuera de horario, te avisaremos antes de guardar. Si cambias el día, la hora o el servicio y la clienta tiene email, le llegará un correo con los nuevos datos y el mismo enlace a su cita.</p>

@php
    $inputClass = 'w-full bg-black border border-[#2A2A2A] px-3 py-2 focus:border-gold focus:outline-none';
    $slotWarning = session('slot_warning');
@endphp

<form method="POST" action="{{ route('admin.appointments.update', $appointment) }}" class="space-y-5 max-w-2xl">
    @csrf
    @method('PUT')

    <div>
        <label for="service_id" class="block text-sm mb-1">Servicio</label>
        <select id="service_id" name="service_id" required class="{{ $inputClass }}" @if ($slotWarning) aria-describedby="slot-warning-text" @endif>
            @foreach ($services as $service)
                <option value="{{ $service->id }}" @selected((int) old('service_id', $appointment->service_id) === $service->id)>{{ $service->name }} ({{ $service->duration_label }}){{ $service->is_active ? '' : ' · inactivo' }}</option>
            @endforeach
        </select>
        @error('service_id') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="date" class="block text-sm mb-1">Fecha</label>
            <input id="date" name="date" type="date" required value="{{ old('date', $appointment->starts_at->toDateString()) }}" class="{{ $inputClass }}" @if ($slotWarning) aria-describedby="slot-warning-text" @endif>
            @error('date') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="time" class="block text-sm mb-1">Hora</label>
            <input id="time" name="time" type="time" step="300" required value="{{ old('time', $appointment->starts_at->format('H:i')) }}" class="{{ $inputClass }}" @if ($slotWarning) aria-describedby="slot-warning-text" @endif>
            @error('time') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="customer_name" class="block text-sm mb-1">Nombre</label>
            <input id="customer_name" name="customer_name" type="text" maxlength="100" required value="{{ old('customer_name', $appointment->customer_name) }}" class="{{ $inputClass }}">
            @error('customer_name') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="customer_phone" class="block text-sm mb-1">Teléfono</label>
            <input id="customer_phone" name="customer_phone" type="tel" maxlength="20" required value="{{ old('customer_phone', $appointment->customer_phone) }}" class="{{ $inputClass }}">
            @error('customer_phone') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="customer_email" class="block text-sm mb-1">Email (opcional: si lo pones, recibirá el aviso del cambio)</label>
        <input id="customer_email" name="customer_email" type="email" maxlength="150" value="{{ old('customer_email', $appointment->customer_email) }}" class="{{ $inputClass }}">
        @error('customer_email') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="notes" class="block text-sm mb-1">Observaciones</label>
        <textarea id="notes" name="notes" maxlength="500" rows="3" class="{{ $inputClass }}">{{ old('notes', $appointment->notes) }}</textarea>
        @error('notes') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- "Guardar cambios" must stay the first submit button of the form:
         pressing Enter in a field submits with the first one, and that must
         never be "Guardar igualmente". --}}
    <div class="flex gap-4 items-center">
        <button type="submit" class="btn-gold">Guardar cambios</button>
        <a href="{{ route('admin.agenda', ['fecha' => $appointment->starts_at->toDateString()]) }}" class="text-gray-300 hover:text-gold">Volver a la agenda</a>
    </div>

    @if ($slotWarning)
        <div id="slot-warning" role="alert" tabindex="-1" autofocus aria-labelledby="slot-warning-title" aria-describedby="slot-warning-text" class="border border-amber-500/50 bg-amber-500/10 text-amber-200 px-4 py-4 space-y-3 focus:outline-none focus:ring-2 focus:ring-amber-400">
            <p id="slot-warning-title" class="font-semibold">Esa hora cae fuera del horario de apertura o no tiene plaza libre para este servicio.</p>
            <p id="slot-warning-text" class="text-sm">No se ha guardado nada. Puedes cambiar el día, la hora o el servicio y pulsar «Guardar cambios», o guardar la cita igualmente a esa hora: quedará en la agenda aunque se supere la capacidad o el horario.</p>
            <button type="submit" name="force" value="{{ $slotWarning }}" class="btn-outline">Guardar igualmente</button>
        </div>
    @endif
</form>
@endsection
