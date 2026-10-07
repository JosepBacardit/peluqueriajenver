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
    // "service_ids" also checks "service_ids.*" (one checkbox too many,
    // repeated, or inactive), not only the array-level rules.
    $fieldAria = fn (string $field): string => $errors->has($field) || $errors->has($field.'.*') ? 'aria-invalid="true" aria-describedby="'.$field.'-error"' : '';
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

    @php
        // PRF-120/PRF-132 (T045/T048): preselects every service the agenda
        // was filtered by (or whose "Cabe" hueco was tapped);
        // "old('service_ids')" still wins when re-displaying the form
        // after a validation error with a different choice.
        $checkedServiceIds = array_map('intval', (array) old('service_ids', $servicios ?? []));
        // Review finding L4: one message for the list and its items, with
        // the id the fieldset points to; aria-invalid on each checkbox
        // (a fieldset does not expose it).
        $servicesError = $errors->first('service_ids') ?: $errors->first('service_ids.*');
        // Review finding L6: the total also without JavaScript (and after
        // a validation error); the script below keeps it live.
        $checkedMinutes = (int) $services->whereIn('id', $checkedServiceIds)->sum('duration_minutes');
        $waitMinutesOf = fn ($service) => \App\Booking\TimeProfile::fromServices([$service])->waitMinutes();
        $checkedWaitMinutes = (int) $services->whereIn('id', $checkedServiceIds)->sum($waitMinutesOf);
    @endphp
    <fieldset id="service_ids" @if ($servicesError) aria-describedby="service_ids-error" @endif>
        <legend class="block text-sm mb-1">Servicios (hasta {{ \App\Models\Appointment::MAX_SERVICES }})</legend>
        <div class="space-y-2">
            @foreach ($services as $service)
                <label class="flex items-center gap-3 min-h-11 cursor-pointer">
                    <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" class="service-checkbox w-5 h-5 shrink-0 accent-gold" data-minutes="{{ $service->duration_minutes }}" data-wait-minutes="{{ $waitMinutesOf($service) }}" @checked(in_array($service->id, $checkedServiceIds, true)) @if ($servicesError) aria-invalid="true" @endif>
                    <span>{{ $service->name }} ({{ $service->duration_with_wait_label }})</span>
                </label>
            @endforeach
        </div>
        <p id="service-total" class="text-sm text-gold-light mt-2 {{ $checkedMinutes > 0 ? '' : 'hidden' }}" aria-live="polite">Duración total: {{ \App\Models\Service::formatDurationWithWait($checkedMinutes, $checkedWaitMinutes) }}</p>
        @if ($servicesError) <p id="service_ids-error" class="text-red-400 text-sm mt-1">{{ $servicesError }}</p> @endif
    </fieldset>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="date" class="block text-sm mb-1">Fecha</label>
            <input id="date" name="date" type="date" required value="{{ old('date', $day->toDateString()) }}" class="{{ $inputClass }}" {!! $fieldAria('date') !!}>
            @error('date') <p id="date-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="time" class="block text-sm mb-1">Hora</label>
            <input id="time" name="time" type="time" step="300" required value="{{ old('time', $time) }}" class="{{ $inputClass }}" {!! $fieldAria('time') !!}>
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
@include('partials.service-total-script', ['targetId' => 'service-total', 'label' => 'Duración total'])
@endif
@endsection
