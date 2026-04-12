@extends('layouts.app')

@section('title', __('servicios.mechas.meta.title'))
@section('description', __('servicios.mechas.meta.description'))
@section('keywords', __('servicios.mechas.meta.keywords'))

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-block mb-6">
            <span class="text-gold text-xs font-semibold tracking-widest uppercase">{{ __('servicios.mechas.hero.subtitle') }}</span>
        </div>

        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            {{ __('servicios.mechas.hero.title') }}
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            {{ __('servicios.mechas.hero.description') }}
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
        <h2 class="section-title text-center mb-8">{{ __('servicios.mechas.sections.que_es.title') }}</h2>
        <p class="text-gray-400 text-lg leading-relaxed text-center mb-12">
            {{ __('servicios.mechas.sections.que_es.content') }}
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">✓</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Efecto Natural</h3>
                <p class="text-gray-400">Creamos mechas que parecen naturales, como si el sol hubiera iluminado tu cabello.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">✨</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Volumen y Movimiento</h3>
                <p class="text-gray-400">Las mechas añaden dimensión y movimiento a tu cabello, realzando su belleza.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">💇</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Menos Mantenimiento</h3>
                <p class="text-gray-400">Las mechas requieren menos retoques que la coloración completa, ahorrando tiempo y dinero.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">🎨</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Personalización</h3>
                <p class="text-gray-400">Cada mezcla de color es personalizada según tu tono de piel y preferencias.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 3: Tipos de mechas -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.mechas.sections.tipos.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            @foreach(__('servicios.mechas.sections.tipos.items') as $tipo)
                <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8">
                    <h3 class="font-serif text-2xl font-bold text-white mb-4">{{ $tipo['name'] }}</h3>
                    <p class="text-gray-400 leading-relaxed">{{ $tipo['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- SECTION 4: Nuestro proceso -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.mechas.sections.proceso.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-12">
            @php $pasos = __('servicios.mechas.sections.proceso.pasos'); @endphp
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

<!-- SECTION 5: Cuidados -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.mechas.sections.cuidados.title') }}</h2>

        <ul class="space-y-4">
            @foreach(__('servicios.mechas.sections.cuidados.items') as $cuidado)
                <li class="flex items-start">
                    <span class="text-gold text-2xl mr-4">→</span>
                    <span class="text-gray-300 text-lg">{{ $cuidado }}</span>
                </li>
            @endforeach
        </ul>

        <div class="mt-12 p-6 bg-[#1A1A1A] border border-gold/30 rounded">
            <p class="text-gray-300 text-center text-sm">
                <strong class="text-gold">Consejo profesional:</strong> Usa champú y acondicionador específicos para cabello teñido para mantener el color más tiempo y tu cabello más saludable.
            </p>
        </div>
    </div>
</section>

<!-- SECTION 6: CTA Reserva -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            ¿Listo para transformar tu cabello?
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Reserva tu cita hoy y obtén un diagnóstico gratuito personalizado.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Quería%20pedir%20cita%20para%20mechas%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                WhatsApp →
            </a>
        </div>
    </div>
</section>

@endsection
