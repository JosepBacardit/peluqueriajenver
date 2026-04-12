@extends('layouts.app')

@section('title', __('servicios.complementarios.meta.title'))
@section('description', __('servicios.complementarios.meta.description'))
@section('keywords', __('servicios.complementarios.meta.keywords'))

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1604654894610-df63bc536371?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            {{ __('servicios.complementarios.hero.title') }}
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            {{ __('servicios.complementarios.hero.subtitle') }}
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                Reservar cita →
            </a>
        </div>
    </div>
</section>

<!-- SECTION 2: Belleza integral -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-8">{{ __('servicios.complementarios.sections.detalles.title') }}</h2>
        <p class="text-gray-400 text-lg leading-relaxed text-center">
            {{ __('servicios.complementarios.sections.detalles.content') }}
        </p>
    </div>
</section>

<!-- SECTION 3: Nuestros servicios -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.complementarios.sections.servicios.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            @foreach(__('servicios.complementarios.sections.servicios.items') as $servicio)
                <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8">
                    <h3 class="font-serif text-2xl font-bold text-white mb-4">{{ $servicio['name'] }}</h3>
                    <p class="text-gray-400 leading-relaxed">{{ $servicio['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- SECTION 4: Características destacadas -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">¿Por qué los detalles importan?</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">👁️</span> Cejas Perfectas
                </h3>
                <p class="text-gray-400 text-sm">Las cejas enmarcan el rostro. Un buen diseño realza tus facciones y completa tu look.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">✨</span> Piel Cuidada
                </h3>
                <p class="text-gray-400 text-sm">La depilación facial profesional deja tu piel suave y cuidada.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">💅</span> Manos Hermosas
                </h3>
                <p class="text-gray-400 text-sm">Unas manicura completa aporta elegancia a tu imagen completa.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">👣</span> Pies Relajados
                </h3>
                <p class="text-gray-400 text-sm">La pedicura es cuidado y relajación en una sesión.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 5: Profesionalismo e higiene -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Estándares de Higiene</h2>

        <div class="space-y-4">
            <div class="flex items-start p-4 bg-[#1A1A1A] border border-[#2A2A2A] rounded">
                <span class="text-gold text-2xl mr-4">✓</span>
                <div>
                    <h3 class="text-white font-bold mb-2">Esterilización Profesional</h3>
                    <p class="text-gray-400 text-sm">Todos nuestros instrumentos se esterilizan y desinfectan según normas profesionales.</p>
                </div>
            </div>

            <div class="flex items-start p-4 bg-[#1A1A1A] border border-[#2A2A2A] rounded">
                <span class="text-gold text-2xl mr-4">✓</span>
                <div>
                    <h3 class="text-white font-bold mb-2">Productos Certificados</h3>
                    <p class="text-gray-400 text-sm">Utilizamos productos certificados y aptos para todos los tipos de piel.</p>
                </div>
            </div>

            <div class="flex items-start p-4 bg-[#1A1A1A] border border-[#2A2A2A] rounded">
                <span class="text-gold text-2xl mr-4">✓</span>
                <div>
                    <h3 class="text-white font-bold mb-2">Ambiente Limpio</h3>
                    <p class="text-gray-400 text-sm">Nuestro salón mantiene los más altos estándares de limpieza.</p>
                </div>
            </div>

            <div class="flex items-start p-4 bg-[#1A1A1A] border border-[#2A2A2A] rounded">
                <span class="text-gold text-2xl mr-4">✓</span>
                <div>
                    <h3 class="text-white font-bold mb-2">Profesionales Capacitados</h3>
                    <p class="text-gray-400 text-sm">Nuestro equipo está capacitado en seguridad e higiene profesional.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 6: CTA Reserva -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1604654894610-df63bc536371?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Completa tu belleza
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Reserva cualquiera de nuestros servicios complementarios y cuidate de pies a cabeza.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Quería%20pedir%20cita%20para%20servicios%20complementarios%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                WhatsApp →
            </a>
        </div>
    </div>
</section>

@endsection
