@extends('layouts.admin')

@section('title', 'Agenda')

@section('content')
@php
    $weekdays = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
@endphp

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-serif text-3xl text-white">Agenda</h1>
        <p class="text-gold-light capitalize">{{ $weekdays[$day->isoWeekday()] }} {{ $day->format('d/m/Y') }}</p>
    </div>
    <a href="{{ route('admin.appointments.create', ['fecha' => $day->toDateString()]) }}" class="btn-gold text-sm">Nueva cita</a>
</div>

<nav aria-label="Cambiar de día" class="flex flex-wrap items-center gap-3 mb-8 text-sm">
    <a href="{{ route('admin.agenda', ['fecha' => $day->subDay()->toDateString()]) }}" class="btn-outline">← Día anterior</a>
    <a href="{{ route('admin.agenda') }}" class="btn-outline">Hoy</a>
    <a href="{{ route('admin.agenda', ['fecha' => $day->addDay()->toDateString()]) }}" class="btn-outline">Día siguiente →</a>
    <form method="GET" action="{{ route('admin.agenda') }}" class="flex items-center gap-2">
        <label for="fecha" class="sr-only">Ir a la fecha</label>
        <input id="fecha" type="date" name="fecha" value="{{ $day->toDateString() }}" class="bg-black border border-[#2A2A2A] px-2 py-1.5">
        <button type="submit" class="text-gold hover:underline">Ir</button>
    </form>
</nav>

@foreach ($blocks as $block)
    <p class="mb-4 border border-amber-500/40 bg-amber-500/10 text-amber-200 px-4 py-2 text-sm">
        {{ $block->isFullClosure() ? 'Cierre total' : 'Capacidad reducida en '.$block->capacity_reduction }}:
        {{ $block->starts_at->format('d/m H:i') }} → {{ $block->ends_at->format('d/m H:i') }}@if ($block->reason) · {{ $block->reason }}@endif
    </p>
@endforeach

@if ($appointments->isEmpty())
    <p class="text-gray-300">No hay citas este día.</p>
@else
    <ul class="space-y-3">
        @foreach ($appointments as $appointment)
            <li class="border border-[#2A2A2A] p-4 {{ $appointment->isConfirmed() ? 'bg-[#111111]' : 'opacity-60' }}">
                <div class="flex flex-wrap justify-between gap-4">
                    <div class="space-y-1">
                        <p class="text-lg">
                            <span class="text-gold font-semibold">{{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }}</span>
                            · {{ $appointment->service_name }}
                        </p>
                        <p>{{ $appointment->customer_name }} · <a href="tel:{{ preg_replace('/[^0-9+]/', '', $appointment->customer_phone) }}" class="hover:text-gold">{{ $appointment->customer_phone }}</a>@if ($appointment->customer_email) · {{ $appointment->customer_email }}@endif</p>
                        @if ($appointment->isConfirmed() && $appointment->customer_email && $appointment->customer_notified_at === null)
                            <p class="text-sm text-amber-300">Correo de confirmación no enviado: el sistema lo reintenta cada 10 minutos. Si sigue así, revisa el email o avisa al cliente por teléfono.</p>
                        @endif
                        @if ($appointment->notes)
                            <p class="text-sm text-gray-400">{{ $appointment->notes }}</p>
                        @endif
                        <p class="text-xs text-gray-400">Origen: {{ $appointment->source->label() }} · Estado: <span class="{{ $appointment->isConfirmed() ? 'text-green-300' : 'text-red-300' }}">{{ $appointment->status->label() }}</span></p>
                    </div>
                    @if ($appointment->isConfirmed())
                        <form method="POST" action="{{ route('admin.appointments.cancel', $appointment) }}" onsubmit="return confirm('¿Cancelar la cita de {{ e($appointment->customer_name) }}? Su hora quedará libre.');">
                            @csrf
                            <button type="submit" class="text-red-300 hover:underline text-sm">Cancelar cita</button>
                        </form>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
@endsection
