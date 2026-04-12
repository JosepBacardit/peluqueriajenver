@extends('layouts.app')

@section('title', __('servicios.color.meta.title'))
@section('description', __('servicios.color.meta.description'))
@section('keywords', __('servicios.color.meta.keywords'))

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1605497788044-5a32c7078486?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-block mb-6">
            <span class="text-gold text-xs font-semibold tracking-widest uppercase">{{ __('servicios.color.hero.subtitle') }}</span>
        </div>

        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            {{ __('servicios.color.hero.title') }}
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            {{ __('servicios.color.hero.description') }}
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                Reservar cita →
            </a>
        </div>
    </div>
</section>

<!-- SECTION 2: ¿Por qué coloración en Jenver? -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-8">{{ __('servicios.color.sections.que_es.title') }}</h2>
        <p class="text-gray-400 text-lg leading-relaxed text-center mb-12">
            {{ __('servicios.color.sections.que_es.content') }}
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">🎨</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Tonalidad Perfecta</h3>
                <p class="text-gray-400">Encontramos el color que mejor complemente tu tono de piel y estilo personal.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">✨</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Productos de Calidad</h3>
                <p class="text-gray-400">Utilizamos tintes profesionales que cuidan tu cabello mientras lo transforman.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">💫</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Cobertura Total</h3>
                <p class="text-gray-400">Cobertura completa de canas y resultados uniformes en todo el cabello.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">💇‍♀️</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Asesoramiento Experto</h3>
                <p class="text-gray-400">Nuestros coloristas asesoran sobre el color más favorecedero para ti.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 3: Tipos de coloración -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('servicios.color.sections.tipos.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            @foreach(__('servicios.color.sections.tipos.items') as $tipo)
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
        <h2 class="section-title text-center mb-12">{{ __('servicios.color.sections.proceso.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            @php $pasos = __('servicios.color.sections.proceso.pasos'); @endphp
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

<!-- SECTION 5: Cuidados del color -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Mantén tu color vibrante</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">🧴</span> Productos Adecuados
                </h3>
                <p class="text-gray-400 text-sm">Usa champú y acondicionador específicos para cabello coloreado. Estos productos ayudan a mantener el color y protegen tu cabello.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">☀️</span> Protección Solar
                </h3>
                <p class="text-gray-400 text-sm">El sol puede desvanecer el color. Usa productos con protección UV o evita la exposición prolongada.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">💧</span> Hidratación
                </h3>
                <p class="text-gray-400 text-sm">El cabello coloreado necesita hidratación extra. Aplica mascarillas profundas semanalmente.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6">
                <h3 class="text-gold font-bold mb-3 flex items-center gap-2">
                    <span class="text-xl">✂️</span> Retoques Regulares
                </h3>
                <p class="text-gray-400 text-sm">Realiza retoques cada 4-6 semanas para mantener el color uniforme y cubrir las raíces nuevas.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 6: CTA Reserva -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1605497788044-5a32c7078486?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Encuentra tu color perfecto
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Consulta con nuestros coloristas y descubre qué tonalidad te favorece más.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Quería%20pedir%20cita%20para%20coloración%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                WhatsApp →
            </a>
        </div>
    </div>
</section>

@endsection
