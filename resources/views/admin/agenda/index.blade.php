@extends('layouts.admin')

@section('title', 'Agenda')

@section('content')
@php
    $weekdays = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];

    $periodLabel = match ($vista) {
        'semana' => 'Semana del '.$weekStart->format('d/m').' al '.$weekEnd->format('d/m/Y'),
        'mes' => ucfirst($month->locale('es')->translatedFormat('F Y')),
        default => $weekdays[$day->isoWeekday()].' '.$day->format('d/m/Y'),
    };
@endphp

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-serif text-3xl text-white">Agenda</h1>
        <p class="text-gold-light capitalize">{{ $periodLabel }}</p>
    </div>
    {{-- Hidden on phones: the floating button below replaces it there,
         always reachable with the thumb without scrolling to the top. --}}
    <a href="{{ route('admin.appointments.create', ['fecha' => $day->toDateString()]) }}" class="hidden md:inline-flex btn-gold text-sm">Nueva cita</a>
</div>

{{-- There is no bottom bar any more (the panel nav is a hamburger now),
     so the floating button only needs to clear the iPhone safe area, not
     a reserved bar height. --}}
<a href="{{ route('admin.appointments.create', ['fecha' => $day->toDateString()]) }}"
   class="md:hidden fixed right-4 z-40 flex items-center justify-center w-14 h-14 rounded-full bg-gold text-black shadow-lg shadow-black/40 hover:bg-gold-light"
   style="bottom: calc(1.5rem + env(safe-area-inset-bottom))"
   aria-label="Nueva cita">
    <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
    </svg>
</a>

{{-- View switcher (PRF-099): "vista" is left out of the Día link because
     it is the default, so existing shared/bookmarked links (and tests)
     that only carry "fecha" keep opening Día exactly as before. --}}
<nav aria-label="Vista de la agenda" class="mb-4 flex gap-2 text-sm">
    <a href="{{ route('admin.agenda', ['fecha' => $day->toDateString()]) }}"
       class="min-h-11 min-w-11 px-3 flex items-center justify-center border {{ $vista === 'dia' ? 'bg-gold text-black border-gold font-semibold' : 'border-[#2A2A2A] text-gray-300 hover:text-gold' }}"
       {!! $vista === 'dia' ? 'aria-current="page"' : '' !!}>Día</a>
    <a href="{{ route('admin.agenda', ['vista' => 'semana', 'fecha' => $day->toDateString()]) }}"
       class="min-h-11 min-w-11 px-3 flex items-center justify-center border {{ $vista === 'semana' ? 'bg-gold text-black border-gold font-semibold' : 'border-[#2A2A2A] text-gray-300 hover:text-gold' }}"
       {!! $vista === 'semana' ? 'aria-current="page"' : '' !!}>Semana</a>
    <a href="{{ route('admin.agenda', ['vista' => 'mes', 'fecha' => $day->toDateString()]) }}"
       class="min-h-11 min-w-11 px-3 flex items-center justify-center border {{ $vista === 'mes' ? 'bg-gold text-black border-gold font-semibold' : 'border-[#2A2A2A] text-gray-300 hover:text-gold' }}"
       {!! $vista === 'mes' ? 'aria-current="page"' : '' !!}>Mes</a>
</nav>

{{-- One compact row (prev/next as icon-only buttons) so it does not wrap
     to two lines at 375px; the date picker goes on its own row below
     (review finding N2). Anterior/Siguiente/Hoy adapt to the active view
     (PRF-104); "Mañana" only makes sense in Día. --}}
<nav aria-label="Cambiar de {{ $vista === 'semana' ? 'semana' : ($vista === 'mes' ? 'mes' : 'día') }}" class="mb-8 text-sm space-y-3">
    <div class="flex items-center gap-2">
        @if ($vista === 'semana')
            <a href="{{ route('admin.agenda', ['vista' => 'semana', 'fecha' => $weekStart->subWeek()->toDateString()]) }}" class="btn-outline w-11 px-0" aria-label="Semana anterior">←</a>
            <a href="{{ route('admin.agenda', ['vista' => 'semana']) }}" class="btn-outline">Hoy</a>
            <a href="{{ route('admin.agenda', ['vista' => 'semana', 'fecha' => $weekStart->addWeek()->toDateString()]) }}" class="btn-outline w-11 px-0" aria-label="Semana siguiente">→</a>
        @elseif ($vista === 'mes')
            <a href="{{ route('admin.agenda', ['vista' => 'mes', 'fecha' => $month->subMonth()->toDateString()]) }}" class="btn-outline w-11 px-0" aria-label="Mes anterior">←</a>
            <a href="{{ route('admin.agenda', ['vista' => 'mes']) }}" class="btn-outline">Hoy</a>
            <a href="{{ route('admin.agenda', ['vista' => 'mes', 'fecha' => $month->addMonth()->toDateString()]) }}" class="btn-outline w-11 px-0" aria-label="Mes siguiente">→</a>
        @else
            <a href="{{ route('admin.agenda', ['fecha' => $day->subDay()->toDateString()]) }}" class="btn-outline w-11 px-0" aria-label="Día anterior">←</a>
            <a href="{{ route('admin.agenda') }}" class="btn-outline">Hoy</a>
            <a href="{{ route('admin.agenda', ['fecha' => \Carbon\CarbonImmutable::today()->addDay()->toDateString()]) }}" class="btn-outline">Mañana</a>
            <a href="{{ route('admin.agenda', ['fecha' => $day->addDay()->toDateString()]) }}" class="btn-outline w-11 px-0" aria-label="Día siguiente">→</a>
        @endif
    </div>
    <form method="GET" action="{{ route('admin.agenda') }}" class="flex items-center gap-2">
        <label for="fecha" class="sr-only">Ir a la fecha</label>
        @if ($vista !== 'dia')
            <input type="hidden" name="vista" value="{{ $vista }}">
        @endif
        <input id="fecha" type="date" name="fecha" value="{{ $day->toDateString() }}" class="bg-black border border-[#2A2A2A] px-2 py-3">
        <button type="submit" class="btn-outline">Ir</button>
    </form>
</nav>

{{-- pb-24 (phones only): keeps the floating "+" button from sitting on
     top of the last appointment card once the list is scrolled to the
     bottom (PRF-093). --}}
<div class="pb-24 md:pb-0">
    @if ($vista === 'semana')
        @include('admin.agenda._week')
    @elseif ($vista === 'mes')
        @include('admin.agenda._month')
    @else
        @include('admin.agenda._day')
    @endif
</div>
@endsection
