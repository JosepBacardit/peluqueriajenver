@extends('layouts.app')

@section('title', __('servicios.peinados_eventos.meta.title'))
@section('description', __('servicios.peinados_eventos.meta.description'))
@section('keywords', __('servicios.peinados_eventos.meta.keywords'))

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1634449571010-02389ed0f9b0?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            {{ __('servicios.peinados_eventos.hero.title') }}
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            {{ __('servicios.peinados_eventos.hero.subtitle') }}
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                Reservar cita →
            </a>
        </div>
    </div>
</section>

<!-- SECTION 2: Tu peinado perfecto -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-8">{{ __('servicios.peinados_eventos.sections.que_es.title') }}</h2>
        <p class="text-gray-400 text-lg leading-relaxed text-center">
            {{ __('servicios.peinados_eventos.sections.que_es.content') }}
        </p>
    </div>
</section>

<!-- SECTION 3: Tipos de eventos -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.peinados_eventos.sections.tipos.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Peinado de Novia -->
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8">
                <div class="text-4xl mb-4">👰</div>
                <h3 class="font-serif text-2xl font-bold text-white mb-4">{{ __('servicios.peinados_eventos.sections.tipos.items.boda.name') }}</h3>
                <p class="text-gray-400 leading-relaxed">{{ __('servicios.peinados_eventos.sections.tipos.items.boda.description') }}</p>
            </div>

            <!-- Peinado Comunión -->
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8">
                <div class="text-4xl mb-4">✨</div>
                <h3 class="font-serif text-2xl font-bold text-white mb-4">{{ __('servicios.peinados_eventos.sections.tipos.items.comunion.name') }}</h3>
                <p class="text-gray-400 leading-relaxed">{{ __('servicios.peinados_eventos.sections.tipos.items.comunion.description') }}</p>
            </div>

            <!-- Peinado para Eventos -->
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8">
                <div class="text-4xl mb-4">🎉</div>
                <h3 class="font-serif text-2xl font-bold text-white mb-4">{{ __('servicios.peinados_eventos.sections.tipos.items.evento.name') }}</h3>
                <p class="text-gray-400 leading-relaxed">{{ __('servicios.peinados_eventos.sections.tipos.items.evento.description') }}</p>
            </div>

            <!-- Peinado Fiesta -->
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8">
                <div class="text-4xl mb-4">💃</div>
                <h3 class="font-serif text-2xl font-bold text-white mb-4">{{ __('servicios.peinados_eventos.sections.tipos.items.fiesta.name') }}</h3>
                <p class="text-gray-400 leading-relaxed">{{ __('servicios.peinados_eventos.sections.tipos.items.fiesta.description') }}</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 4: Nuestro proceso -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.peinados_eventos.sections.proceso.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            @php $pasos = __('servicios.peinados_eventos.sections.proceso.pasos'); @endphp
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

<!-- SECTION 5: Lo que incluye -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Lo que ofrecemos</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">💇‍♀️</span> Peinado Personalizado
                </h3>
                <p class="text-gray-400 text-sm">Diseño exclusivo adaptado a tu rostro y estilo personal.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">👰</span> Prueba Previa
                </h3>
                <p class="text-gray-400 text-sm">Realizamos una prueba antes del evento para asegurar tu satisfacción.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">💄</span> Maquillaje Complementario
                </h3>
                <p class="text-gray-400 text-sm">Ofrecemos servicios de maquillaje para completar tu look.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">⏱️</span> Duración Todo el Día
                </h3>
                <p class="text-gray-400 text-sm">Utilizamos productos profesionales para que tu peinado aguante toda la jornada.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 6: CTA Reserva -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1634449571010-02389ed0f9b0?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Tu día especial comienza aquí
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Contacta con nosotros para agendar tu cita y planificar tu peinado perfecto.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Quería%20consultar%20sobre%20peinados%20para%20eventos%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                WhatsApp →
            </a>
        </div>
    </div>
</section>

@endsection
