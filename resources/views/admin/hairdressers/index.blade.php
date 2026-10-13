@extends('layouts.admin')

@section('title', 'Peluqueras')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <h1 class="font-serif text-3xl text-white">Peluqueras</h1>
    <a href="{{ route('admin.hairdressers.create') }}" class="btn-gold text-sm">Nueva peluquera</a>
</div>

<p class="text-sm text-gray-400 mb-4">Las clientas ven el nombre al reservar. Se atienden tantas citas a la vez como peluqueras activas. Las peluqueras no se borran: desactívalas si dejan de trabajar en el salón. Para una baja o un día libre, añade un cierre de esa peluquera en «Cierres».</p>

<ul class="space-y-3">
    @foreach ($hairdressers as $hairdresser)
        <li class="border border-[#2A2A2A] p-4 {{ $hairdresser->is_active ? '' : 'opacity-60' }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-white font-semibold">{{ $hairdresser->name }}</p>
                    <p class="text-sm text-gray-400">{{ $hairdresser->is_active ? 'Activa' : 'Inactiva' }}</p>
                </div>
                <a href="{{ route('admin.hairdressers.edit', $hairdresser) }}" class="btn-outline text-sm" aria-label="Editar a {{ $hairdresser->name }}">Editar</a>
            </div>
        </li>
    @endforeach
</ul>
@endsection
