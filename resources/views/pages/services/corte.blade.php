@extends('layouts.app')

@section('title', __('servicios.corte.meta.title'))
@section('description', __('servicios.corte.meta.description'))
@section('keywords', __('servicios.corte.meta.keywords'))

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            {{ __('servicios.corte.hero.title') }}
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            {{ __('servicios.corte.hero.subtitle') }}
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                Reservar cita →
            </a>
        </div>
    </div>
</section>

<!-- SECTION 2: La importancia del corte -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-8">{{ __('servicios.corte.sections.que_es.title') }}</h2>
        <p class="text-gray-400 text-lg leading-relaxed text-center">
            {{ __('servicios.corte.sections.que_es.content') }}
        </p>
    </div>
</section>

<!-- SECTION 3: Servicios de corte -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.corte.sections.tipos.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            @foreach(__('servicios.corte.sections.tipos.items') as $tipo)
                <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8">
                    <h3 class="font-serif text-2xl font-bold text-white mb-4">{{ $tipo['name'] }}</h3>
                    <p class="text-gray-400 leading-relaxed">{{ $tipo['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- SECTION 4: ¿Por qué elegirnos? -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">¿Por qué Peluquería Jenver?</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">✂️</span> Profesionales Experimentados
                </h3>
                <p class="text-gray-400 text-sm">Nuestros barberos y estilistas tienen años de experiencia en todas las técnicas de corte.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">📐</span> Estudio Personalizado
                </h3>
                <p class="text-gray-400 text-sm">Analizamos la forma de tu cara para elegir el corte más favorable.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">👨‍👩‍👧</span> Para Toda la Familia
                </h3>
                <p class="text-gray-400 text-sm">Cortamos a hombres, mujeres y niños con la máxima profesionalidad.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">💇</span> Mantenimiento Fácil
                </h3>
                <p class="text-gray-400 text-sm">Te asesoramos sobre cómo mantener tu corte en casa.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 5: CTA Reserva -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Un buen corte transforma tu look
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Reserva tu cita con nuestros profesionales y descubre la diferencia de un corte experto.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Quería%20pedir%20cita%20para%20corte%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                WhatsApp →
            </a>
        </div>
    </div>
</section>

@endsection
