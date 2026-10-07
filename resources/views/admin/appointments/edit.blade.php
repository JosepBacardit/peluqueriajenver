@extends('layouts.admin')

@section('title', 'Editar cita')

@section('content')
<h1 class="font-serif text-3xl text-white mb-2">Editar cita</h1>
<p class="text-sm text-gray-400 mb-6">Puedes cambiar el día, la hora, el servicio y los datos de la clienta. Se comprueban el horario y la capacidad; si la nueva hora está llena o fuera de horario, te avisaremos antes de guardar. Si cambias el día, la hora o el servicio y la clienta tiene email, le llegará un correo con los nuevos datos. Si cambias su email, le llegará a la dirección nueva un enlace nuevo a su cita y el anterior dejará de funcionar.</p>

@php
    // py-3 (not py-2): every field, including the date/time pickers, meets
    // the 44px touch target (review finding N3).
    $inputClass = 'w-full bg-black border border-[#2A2A2A] px-3 py-3 focus:border-gold focus:outline-none';
    $slotWarning = session('slot_warning');
    $slotReason = App\Booking\UnavailabilityReason::tryFrom($slotWarning['reason'] ?? '');
    // aria-invalid and aria-describedby for a field: its own error message
    // and, for the fields that choose the time, the "save anyway" warning.
    $fieldAria = function (string $field, bool $choosesTime = false) use ($errors, $slotWarning): string {
        $hasError = $errors->has($field) || $errors->has($field.'.*');
        $describedBy = array_filter([
            $hasError ? $field.'-error' : null,
            $choosesTime && $slotWarning ? 'slot-warning-text' : null,
        ]);

        return trim(($hasError ? 'aria-invalid="true" ' : '').($describedBy === [] ? '' : 'aria-describedby="'.implode(' ', $describedBy).'"'));
    };
@endphp

<form method="POST" action="{{ route('admin.appointments.update', $appointment) }}" class="space-y-5 max-w-2xl">
    @csrf
    @method('PUT')
    <input type="hidden" name="version" value="{{ old('version', $appointment->updated_at?->getTimestamp()) }}">
    {{-- Review finding M1: carried through to update() so it redirects back
         to the view/date the salon was on, not always vista Día. --}}
    @if ($volver)
        <input type="hidden" name="volver" value="{{ old('volver', $volver) }}">
    @endif

    @php
        // PRF-129: every service the appointment already has is checked
        // (the bug this task fixes: the old single <select> only ever
        // sent the first one back); "old('service_ids')" wins after a
        // validation error with a different choice made in the form.
        $checkedServiceIds = array_map('intval', (array) old('service_ids', $selectedIds));
        // Review finding L4: one message for the list and its items, with
        // the id the fieldset points to; aria-invalid on each checkbox
        // (a fieldset does not expose it).
        $servicesError = $errors->first('service_ids') ?: $errors->first('service_ids.*');
        $servicesDescribedBy = implode(' ', array_filter([$servicesError ? 'service_ids-error' : null, $slotWarning ? 'slot-warning-text' : null]));
        // Review finding L6: the minutes each service would add, the way
        // RescheduleAppointment counts them (a service the appointment
        // already has keeps its booked length), for the total shown with
        // and without JavaScript.
        $frozenMinutes = $appointment->items->pluck('duration_minutes', 'service_id');
        $minutesOf = fn ($service) => (int) ($frozenMinutes[$service->id] ?? $service->duration_minutes);
        $checkedMinutes = (int) $services->whereIn('id', $checkedServiceIds)->sum($minutesOf);
    @endphp
    <fieldset id="service_ids" @if ($servicesDescribedBy !== '') aria-describedby="{{ $servicesDescribedBy }}" @endif>
        <legend class="block text-sm mb-1">Servicios (hasta {{ \App\Models\Appointment::MAX_SERVICES }})</legend>
        <div class="space-y-2">
            @foreach ($services as $service)
                <label class="flex items-center gap-3 min-h-11 cursor-pointer">
                    <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" class="service-checkbox w-5 h-5 shrink-0 accent-gold" data-minutes="{{ $minutesOf($service) }}" @checked(in_array($service->id, $checkedServiceIds, true)) @if ($servicesError) aria-invalid="true" @endif>
                    <span>{{ $service->name }} ({{ $service->duration_label }}){{ $service->is_active ? '' : ' · inactivo' }}</span>
                </label>
            @endforeach
        </div>
        <p id="service-total" class="text-sm text-gold-light mt-2 {{ $checkedMinutes > 0 ? '' : 'hidden' }}" aria-live="polite">Duración total: {{ \App\Models\Service::formatDuration($checkedMinutes) }}</p>
        @if ($servicesError) <p id="service_ids-error" class="text-red-400 text-sm mt-1">{{ $servicesError }}</p> @endif
    </fieldset>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="date" class="block text-sm mb-1">Fecha</label>
            <input id="date" name="date" type="date" required value="{{ old('date', $appointment->starts_at->toDateString()) }}" class="{{ $inputClass }}" {!! $fieldAria('date', true) !!}>
            @error('date') <p id="date-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="time" class="block text-sm mb-1">Hora</label>
            <input id="time" name="time" type="time" step="300" required value="{{ old('time', $appointment->starts_at->format('H:i')) }}" class="{{ $inputClass }}" {!! $fieldAria('time', true) !!}>
            @error('time') <p id="time-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="customer_name" class="block text-sm mb-1">Nombre</label>
            <input id="customer_name" name="customer_name" type="text" maxlength="100" required value="{{ old('customer_name', $appointment->customer_name) }}" class="{{ $inputClass }}" {!! $fieldAria('customer_name') !!}>
            @error('customer_name') <p id="customer_name-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="customer_phone" class="block text-sm mb-1">Teléfono</label>
            <input id="customer_phone" name="customer_phone" type="tel" maxlength="20" required value="{{ old('customer_phone', $appointment->customer_phone) }}" class="{{ $inputClass }}" {!! $fieldAria('customer_phone') !!}>
            @error('customer_phone') <p id="customer_phone-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="customer_email" class="block text-sm mb-1">Email (opcional: si lo pones, recibirá los avisos de su cita; si lo cambias, el enlace anterior a su cita deja de funcionar)</label>
        <input id="customer_email" name="customer_email" type="email" maxlength="150" value="{{ old('customer_email', $appointment->customer_email) }}" class="{{ $inputClass }}" {!! $fieldAria('customer_email') !!}>
        @error('customer_email') <p id="customer_email-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="notes" class="block text-sm mb-1">Observaciones</label>
        <textarea id="notes" name="notes" maxlength="500" rows="3" class="{{ $inputClass }}" {!! $fieldAria('notes') !!}>{{ old('notes', $appointment->notes) }}</textarea>
        @error('notes') <p id="notes-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- "Guardar cambios" must stay the first submit button of the form:
         pressing Enter in a field submits with the first one, and that must
         never be "Guardar igualmente". --}}
    @php
        // $volver is already validated ("vista:fecha" or null) by the
        // controller, so splitting it here is safe (review finding M1).
        $volverParts = $volver ? explode(':', $volver, 2) : null;
        $backRoute = $volverParts ? ['vista' => $volverParts[0], 'fecha' => $volverParts[1]] : ['fecha' => $appointment->starts_at->toDateString()];
    @endphp
    <div class="flex gap-4 items-center">
        <button type="submit" class="btn-gold">Guardar cambios</button>
        <a href="{{ route('admin.agenda', $backRoute) }}" class="text-gray-300 hover:text-gold">Volver a la agenda</a>
    </div>

    @if ($slotWarning)
        <div id="slot-warning" role="alert" tabindex="-1" aria-labelledby="slot-warning-title" aria-describedby="slot-warning-text" class="border border-amber-500/50 bg-amber-500/10 text-amber-200 px-4 py-4 space-y-3 focus:outline-none focus:ring-2 focus:ring-amber-400">
            <p id="slot-warning-title" class="font-semibold">{{ $slotReason?->adminMessage() ?? 'Esa hora no está disponible para este servicio.' }}</p>
            <p id="slot-warning-text" class="text-sm">No se ha guardado nada. Puedes cambiar el día, la hora o el servicio y pulsar «Guardar cambios», o guardar la cita igualmente a esa hora: quedará en la agenda aunque se supere la capacidad o el horario.</p>
            <button type="submit" name="force" value="{{ $slotWarning['key'] }}" class="btn-outline">Guardar igualmente</button>
        </div>
        {{-- Move the focus to the warning so keyboard and screen reader
             users learn that nothing was saved (autofocus is not reliable
             on a non-form element). --}}
        <script>document.getElementById('slot-warning').focus();</script>
    @endif
</form>
@include('partials.service-total-script', ['targetId' => 'service-total', 'label' => 'Duración total'])
@endsection
