@extends('layouts.admin')

@section('title', 'Servicios')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <h1 class="font-serif text-3xl text-white">Servicios</h1>
    <a href="{{ route('admin.services.create') }}" class="btn-gold text-sm">Nuevo servicio</a>
</div>

<p class="text-sm text-gray-400 mb-4">El precio es interno: nunca se muestra en la web ni en los correos a clientes. Los servicios no se borran; desactívalos si ya no se ofrecen.</p>

@if ($services->isEmpty())
    <p class="text-gray-300">Todavía no hay servicios. Crea el primero para poder recibir reservas.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="text-gray-400 border-b border-[#2A2A2A]">
                <tr>
                    <th class="py-2 pr-4">Orden</th>
                    <th class="py-2 pr-4">Nombre</th>
                    <th class="py-2 pr-4">Duración</th>
                    <th class="py-2 pr-4">Precio interno</th>
                    <th class="py-2 pr-4">Reservable online</th>
                    <th class="py-2 pr-4">Activo</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($services as $service)
                    <tr class="border-b border-[#1A1A1A] {{ $service->is_active ? '' : 'text-gray-500' }}">
                        <td class="py-2 pr-4">{{ $service->sort_order }}</td>
                        <td class="py-2 pr-4">{{ $service->name }}</td>
                        <td class="py-2 pr-4">{{ $service->duration_label }}</td>
                        <td class="py-2 pr-4">{{ $service->price_cents === null ? '—' : number_format($service->price_cents / 100, 2, ',', '.').' €' }}</td>
                        <td class="py-2 pr-4">{{ $service->is_bookable_online ? 'Sí' : 'No' }}</td>
                        <td class="py-2 pr-4">{{ $service->is_active ? 'Sí' : 'No' }}</td>
                        <td class="py-2 text-right"><a href="{{ route('admin.services.edit', $service) }}" class="text-gold hover:underline">Editar</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
