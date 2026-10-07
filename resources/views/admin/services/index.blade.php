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
    {{-- Cards at every width (not just mobile): a salon's service list is
         short, so a table added nothing on desktop and forced horizontal
         scroll on a phone. --}}
    <ul class="space-y-3">
        @foreach ($services as $service)
            <li class="border border-[#2A2A2A] p-4 {{ $service->is_active ? '' : 'opacity-60' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-white font-semibold">{{ $service->name }}</p>
                        <p class="text-sm text-gray-400">{{ $service->duration_with_wait_label }} · Orden {{ $service->sort_order }}</p>
                    </div>
                    <a href="{{ route('admin.services.edit', $service) }}" class="btn-outline text-sm">Editar</a>
                </div>
                <dl class="mt-3 grid grid-cols-3 gap-3 text-sm">
                    <div>
                        <dt class="text-gray-400">Precio interno</dt>
                        <dd class="text-white">{{ $service->price_cents === null ? '—' : number_format($service->price_cents / 100, 2, ',', '.').' €' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">Reservable online</dt>
                        <dd class="text-white">{{ $service->is_bookable_online ? 'Sí' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">Activo</dt>
                        <dd class="text-white">{{ $service->is_active ? 'Sí' : 'No' }}</dd>
                    </div>
                </dl>
            </li>
        @endforeach
    </ul>
@endif
@endsection
