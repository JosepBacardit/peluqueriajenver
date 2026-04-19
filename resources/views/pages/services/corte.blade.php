@extends('layouts.app')

@section('title', __('servicios.corte.meta.title'))
@section('description', __('servicios.corte.meta.description'))
@section('keywords', __('servicios.corte.meta.keywords'))

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1534639446674-ce2a6375cd51?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-block mb-6">
            <span class="text-gold text-xs font-semibold tracking-widest uppercase">{{ __('servicios.corte.hero.subtitle') }}</span>
        </div>

        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            {{ __('servicios.corte.hero.title') }}
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            {{ __('servicios.corte.hero.description') }}
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                {{ __('home.hero.cta_primary') }}
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Quería%20pedir%20cita%20para%20corte%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base">
                WhatsApp →
            </a>
        </div>
    </div>
</section>

<!-- SECTION 2: Introducción -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-8">{{ __('servicios.corte.sections.que_es.title') }}</h2>
        <p class="text-gray-400 text-lg leading-relaxed text-center mb-12">
            {{ __('servicios.corte.sections.que_es.content') }}
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            @foreach(__('servicios.corte.sections.ventajas.items') as $ventaja)
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8 rounded">
                <div class="text-4xl text-gold mb-4">{{ $ventaja['icon'] }}</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">{{ $ventaja['title'] }}</h3>
                <p class="text-gray-400">{{ $ventaja['description'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- SECTION 3: Servicios incluidos -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.corte.sections.tipos.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
            @foreach(__('servicios.corte.sections.tipos.items') as $tipo)
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8">
                <h3 class="font-serif text-2xl font-bold text-white mb-4">{{ $tipo['name'] }}</h3>
                <p class="text-gray-400 leading-relaxed mb-4">{{ $tipo['description'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- SECTION 4: Proceso -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.corte.sections.proceso.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            @php $pasos = __('servicios.corte.sections.proceso.pasos'); @endphp
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

<!-- SECTION 5: Precios -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Precios</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            @foreach(__('servicios.corte.precios') as $servicio => $precio)
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 flex items-center justify-between">
                <h4 class="font-serif text-lg font-bold text-white capitalize">{{ str_replace('_', ' ', $servicio) }}</h4>
                <p class="text-gold font-bold text-lg">{{ $precio }}</p>
            </div>
            @endforeach
        </div>

        <div class="text-center">
            <p class="text-gray-400">{!! __('home.servicios.help_text') !!}</p>
        </div>
    </div>
</section>

<!-- SECTION 6: CTA Final -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1534639446674-ce2a6375cd51?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Un corte profesional cambia tu look
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Contamos con especialistas en cortes para hombre, mujer y niños. Tu satisfacción es nuestro objetivo.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                {{ __('home.reserva.phone_cta') }}
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Quería%20pedir%20cita%20para%20corte%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                {{ __('home.reserva.whatsapp_cta') }}
            </a>
        </div>
    </div>
</section>

@endsection
