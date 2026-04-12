@extends('layouts.app')

@section('title', __('servicios.afro_rizos.meta.title'))
@section('description', __('servicios.afro_rizos.meta.description'))
@section('keywords', __('servicios.afro_rizos.meta.keywords'))

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1573166475912-1ed8b4f093d2?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            {{ __('servicios.afro_rizos.hero.title') }}
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            {{ __('servicios.afro_rizos.hero.description') }}
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                Reservar cita →
            </a>
        </div>
    </div>
</section>

<!-- SECTION 2: Tu belleza natural -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-8">{{ __('servicios.afro_rizos.sections.que_es.title') }}</h2>
        <p class="text-gray-400 text-lg leading-relaxed text-center">
            {{ __('servicios.afro_rizos.sections.que_es.content') }}
        </p>
    </div>
</section>

<!-- SECTION 3: Servicios para cabello afro -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.afro_rizos.sections.tipos.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            @foreach(__('servicios.afro_rizos.sections.tipos.items') as $tipo)
                <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8">
                    <h3 class="font-serif text-2xl font-bold text-white mb-4">{{ $tipo['name'] }}</h3>
                    <p class="text-gray-400 leading-relaxed">{{ $tipo['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- SECTION 4: Cuidados para cabello afro y rizos -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.afro_rizos.sections.cuidados.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach(__('servicios.afro_rizos.sections.cuidados.items') as $cuidado_key => $cuidado)
                <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                    <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                        <span class="text-xl">💧</span>
                    </h3>
                    <p class="text-gray-400 text-sm">{{ $cuidado }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-8 p-6 bg-gold/10 border border-gold/30 rounded">
            <p class="text-gray-300 text-center">
                <strong class="text-gold">Especialistas en cabello afro:</strong> Tenemos años de experiencia cuidando diferentes texturas de cabello. Somos referencia en la zona por nuestro trato especializado.
            </p>
        </div>
    </div>
</section>

<!-- SECTION 5: ¿Por qué somos especialistas? -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Nuestra experiencia</h2>

        <div class="space-y-4">
            <div class="flex items-start">
                <span class="text-gold text-3xl mr-4">✓</span>
                <div>
                    <h3 class="text-white font-bold mb-2">Más de 10 años de experiencia</h3>
                    <p class="text-gray-400">Llevamos años especializándonos en cabello afro y rizado, conocemos cada detalle.</p>
                </div>
            </div>

            <div class="flex items-start">
                <span class="text-gold text-3xl mr-4">✓</span>
                <div>
                    <h3 class="text-white font-bold mb-2">Productos de calidad</h3>
                    <p class="text-gray-400">Usamos productos especializados que cuidan y respetan la naturaleza del cabello afro.</p>
                </div>
            </div>

            <div class="flex items-start">
                <span class="text-gold text-3xl mr-4">✓</span>
                <div>
                    <h3 class="text-white font-bold mb-2">Referencia en la zona</h3>
                    <p class="text-gray-400">Somos la peluquería de referencia para cabello afro en la comarca.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 6: CTA Reserva -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1573166475912-1ed8b4f093d2?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Tu cabello merece lo mejor
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Confía en nuestros especialistas para cuidar y embellecer tu cabello afro.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Quería%20pedir%20cita%20para%20cabello%20afro%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                WhatsApp →
            </a>
        </div>
    </div>
</section>

@endsection
