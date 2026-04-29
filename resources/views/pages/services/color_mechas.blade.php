@extends('layouts.app')

@section('title', 'Mechas y Balayage en Montcada i Reixac | Peluquería Jenver')
@section('meta_description', 'Balayage, babylights y mechas clásicas en Montcada i Reixac. Coloristas expertas. Diagnóstico gratuito incluido. Reserva tu cita.')
@section('canonical', 'https://www.peluqueriajenver.com/color-y-mechas')
@section('keywords', 'color cabello, mechas, balayage, babylights, coloración capilar, Montcada i Reixac')

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('{{ asset('images/color-mechas.png') }}');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-block mb-6">
            <span class="text-gold text-xs font-semibold tracking-widest uppercase">✦ Especialistas en color</span>
        </div>

        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            Color y Mechas Profesionales
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            Transforma tu look con técnicas de coloración y mechas. Desde balayage natural hasta cambios radicales, nuestros coloristas crean resultados personalizados.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                Reservar cita →
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20agendar%20cita%20para%20color%20y%20mechas%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base">
                WhatsApp
            </a>
        </div>
    </div>
</section>

<!-- SECTION 2: Servicios incluidos -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Nuestros servicios de color y mechas</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            @php
            $servicios = [
                ['nombre' => 'Balayage', 'desc' => 'Técnica de mano alzada que crea un efecto degradado muy natural y personalizado.', 'icon' => '✨'],
                ['nombre' => 'Babylights', 'desc' => 'Mechas finísimas que imitan los tonos naturales del cabello para un resultado delicado.', 'icon' => '💫'],
                ['nombre' => 'Mechas Clásicas', 'desc' => 'Las mechas tradicionales con separación definida para un contraste más marcado.', 'icon' => '✦'],
                ['nombre' => 'Coloración Completa', 'desc' => 'Cambio total de color desde raíz a puntas con técnica profesional.', 'icon' => '🎨'],
                ['nombre' => 'Retoque de Raíz', 'desc' => 'Coloración solo en raíces para mantener el color y cubrir canas.', 'icon' => '💇‍♀️'],
                ['nombre' => 'Decoloración', 'desc' => 'Aclarado progresivo y seguro para lograr tonos más claros.', 'icon' => '✨'],
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
                'Diagnóstico gratuito del cabello',
                'Consulta de color y diseño',
                'Prueba de sensibilidad si es necesario',
                'Aplicación profesional',
                'Cuidados post-tratamiento',
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
                <div class="text-4xl text-gold mb-4">🎨</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Coloristas Expertas</h3>
                <p class="text-gray-400">Nuestras especialistas tienen años de experiencia en técnicas de color y mechas.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">✨</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Productos Premium</h3>
                <p class="text-gray-400">Utilizamos tintes profesionales que cuidan tu cabello mientras lo transforman.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">💎</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Resultados Personalizados</h3>
                <p class="text-gray-400">Diseñamos cada color y técnica adaptados a tu tipo de cabello y tono de piel.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">⏱</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Mantenimiento Fácil</h3>
                <p class="text-gray-400">Te asesoramos sobre cuidados para que tu color dure más y se vea siempre fresco.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 5: Precios Orientativos -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Tarifas Orientativas</h2>
        @include('partials.precios', ['precios' => [
            ['Balayage', 'desde 80€'],
            ['Babylights', 'desde 70€'],
            ['Mechas clásicas', 'desde 50€'],
            ['Coloración completa', 'desde 45€'],
            ['Retoque de raíz', 'desde 30€'],
            ['Decoloración', 'desde 60€'],
        ]])
    </div>
</section>

<!-- SECTION 7: Preguntas Frecuentes -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Preguntas Frecuentes</h2>
        @include('partials.faq', ['preguntas' => [
            ['q' => '¿Cuánto dura un balayage?', 'a' => 'Entre 3 y 6 meses sin retoque. El efecto degradado hace que el crecimiento sea imperceptible.'],
            ['q' => '¿Necesito decolorarme para hacerme mechas?', 'a' => 'Depende de tu color natural. En el diagnóstico gratuito te explicamos qué técnica es la mejor para ti.'],
            ['q' => '¿Cuánto dura la cita para un balayage?', 'a' => 'Entre 2 y 4 horas según la longitud y la técnica. Te lo confirmamos al reservar.'],
        ]])
    </div>
</section>

<!-- SECTION 8: CTA Final -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('{{ asset('images/color-mechas-cta.png') }}');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Encuentra tu color perfecto
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Nuestros coloristas diseñarán el look ideal para ti. ¡Reserva tu cita ahora!
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20agendar%20cita%20para%20color%20y%20mechas%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                WhatsApp →
            </a>
        </div>
    </div>
</section>



@endsection
