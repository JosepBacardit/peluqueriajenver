@extends('layouts.app')

@section('title', 'Peinados para Eventos | Peluquería Jenver')
@section('description', 'Peinados profesionales para bodas, eventos y celebraciones especiales. Diseños personalizados y pruebas previas.')
@section('keywords', 'peinado boda, peinados eventos, peinado novia, peluquería eventos, peinado comunión')

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1519182308596-b60b5d92b8e7?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-block mb-6">
            <span class="text-gold text-xs font-semibold tracking-widest uppercase">👑 Tu día especial</span>
        </div>

        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            Peinados para Eventos
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            Tu momento especial merece un peinado perfecto. Desde bodas hasta eventos, creamos diseños únicos que resalten tu belleza.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                Reservar cita →
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20agendar%20cita%20para%20un%20peinado%20de%20evento%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base">
                WhatsApp
            </a>
        </div>
    </div>
</section>

<!-- SECTION 2: Servicios incluidos -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Nuestros peinados para eventos</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
            @php
            $servicios = [
                ['nombre' => 'Peinado para Bodas', 'desc' => 'Diseño exclusivo para tu día especial. Incluye prueba previa y asesoramiento profesional. Duración garantizada toda la jornada.', 'icon' => '💍'],
                ['nombre' => 'Peinado para Eventos', 'desc' => 'Galas, cócteles, cumpleaños. Diseños adaptados a cada ocasión y a tu estilo personal.', 'icon' => '✨'],
                ['nombre' => 'Recogidos', 'desc' => 'Moños, recogidos clásicos y modernos. Acabado profesional y duradero.', 'icon' => '🎀'],
                ['nombre' => 'Ondas y Rizos', 'desc' => 'Ondas glamurosas y rizos definidos para un look sofisticado y elegante.', 'icon' => '〰️'],
            ];
            @endphp

            @foreach($servicios as $servicio)
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8 rounded">
                <div class="text-4xl text-gold mb-4">{{ $servicio['icon'] }}</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">{{ $servicio['nombre'] }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ $servicio['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- SECTION 3: Proceso -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Nuestro proceso</h2>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            @php
            $pasos = [
                'Consulta previa para tus expectativas',
                'Búsqueda de inspiración y referentes',
                'Prueba del peinado antes del evento',
                'Ajustes y perfeccionamiento',
                'Maquillaje complementario si lo deseas',
            ];
            @endphp

            @foreach($pasos as $paso)
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gold text-black font-bold mb-4 mx-auto">
                    {{ $loop->iteration }}
                </div>
                <p class="text-gray-300 text-sm font-semibold">{{ $paso }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- SECTION 4: Por qué elegirnos -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">¿Por qué Peluquería Jenver?</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">👑</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Diseño Exclusivo</h3>
                <p class="text-gray-400">Creamos un estilo único pensado especialmente para ti y tu ocasión especial.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">✓</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Prueba Previa</h3>
                <p class="text-gray-400">Realizamos una prueba completa antes del evento para tu tranquilidad y confianza.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">⏰</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Larga Duración</h3>
                <p class="text-gray-400">Nuestros peinados aguantan toda la jornada sin perder forma ni estilo.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">💇‍♀️</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Manos Expertas</h3>
                <p class="text-gray-400">Peluqueros con años de experiencia en eventos especiales y momentos importantes.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 5: CTA Final -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1519182308596-b60b5d92b8e7?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Tu día especial merece un peinado perfecto
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Contacta ahora para agendar tu cita y prueba de peinado.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20agendar%20cita%20para%20un%20peinado%20de%20evento%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                WhatsApp →
            </a>
        </div>
    </div>
</section>

@endsection
