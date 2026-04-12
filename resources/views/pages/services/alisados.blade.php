@extends('layouts.app')

@section('title', __('servicios.alisados.meta.title'))
@section('description', __('servicios.alisados.meta.description'))
@section('keywords', __('servicios.alisados.meta.keywords'))

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1522338242992-e1a54906a8da?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            {{ __('servicios.alisados.hero.title') }}
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            {{ __('servicios.alisados.hero.description') }}
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                Reservar cita →
            </a>
        </div>
    </div>
</section>

<!-- SECTION 2: ¿Qué es? -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-8">{{ __('servicios.alisados.sections.que_es.title') }}</h2>
        <p class="text-gray-400 text-lg leading-relaxed text-center">
            {{ __('servicios.alisados.sections.que_es.content') }}
        </p>
    </div>
</section>

<!-- SECTION 3: Tipos de alisado -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.alisados.sections.tipos.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach(__('servicios.alisados.sections.tipos.items') as $tipo)
                <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8">
                    <h3 class="font-serif text-2xl font-bold text-white mb-4">{{ $tipo['name'] }}</h3>
                    <p class="text-gray-400 leading-relaxed">{{ $tipo['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- SECTION 4: Cuidados post-tratamiento -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.alisados.sections.cuidados.title') }}</h2>

        <ul class="space-y-4">
            @foreach(__('servicios.alisados.sections.cuidados.items') as $cuidado_key => $cuidado)
                <li class="flex items-center p-4 bg-[#1A1A1A] border border-[#2A2A2A] rounded">
                    <span class="text-gold text-2xl mr-4">✓</span>
                    <span class="text-gray-300">{{ $cuidado }}</span>
                </li>
            @endforeach
        </ul>

        <div class="mt-8 p-6 bg-gold/10 border border-gold/30 rounded">
            <p class="text-gray-300 text-center">
                <strong class="text-gold">Nota importante:</strong> Los resultados mejorarán progresivamente las primeras semanas. Sigue nuestras recomendaciones para obtener el mejor resultado.
            </p>
        </div>
    </div>
</section>

<!-- SECTION 5: CTA Reserva -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1522338242992-e1a54906a8da?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Cabello liso y sedoso
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Transforma tu cabello con nuestros tratamientos profesionales de alisado.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Quería%20pedir%20cita%20para%20alisado%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                WhatsApp →
            </a>
        </div>
    </div>
</section>

@endsection
