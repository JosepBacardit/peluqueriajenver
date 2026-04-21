@extends('layouts.app')

@section('title', __('home.meta.title'))
@section('description', __('home.meta.description'))
@section('keywords', __('home.meta.keywords'))

@section('content')

<!-- SECTION 1: Hero -->
<section id="hero" class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1560066984-138dadb4c035?w=1920&q=80');">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-black/60"></div>

    <!-- Content -->
    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <!-- Location tag -->
        <div class="inline-block mb-6">
            <span class="text-gold text-xs font-semibold tracking-widest uppercase">{{ __('home.hero.location') }}</span>
        </div>

        <!-- H1 with primary keywords -->
        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            {!! __('home.hero.title') !!}
        </h1>

        <!-- Subtitle -->
        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            {!! __('home.hero.subtitle') !!}
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                {{ __('home.hero.cta_primary') }}
            </a>
            <a href="#servicios" class="btn-outline text-sm md:text-base">
                {{ __('home.hero.cta_secondary') }}
            </a>
        </div>

        <!-- Discount Email Form -->
        <div class="mt-8 pt-8 border-t border-white/10 max-w-md mx-auto w-full">
            <div id="discount-form-wrap">
                <p class="text-gold text-xs font-semibold tracking-widest uppercase mb-3">
                    {{ __('home.descuento.label') }}
                </p>
                <form id="discount-form" class="flex flex-col sm:flex-row gap-3" novalidate>
                    @csrf
                    <input
                        type="email"
                        id="discount-email"
                        name="email"
                        placeholder="{{ __('home.descuento.placeholder') }}"
                        required
                        class="flex-1 bg-white/10 border border-white/20 text-white placeholder-white/40
                               px-4 py-2.5 text-sm focus:outline-none focus:border-gold focus:ring-1
                               focus:ring-gold/50 backdrop-blur-sm transition-colors duration-200"
                    >
                    <button type="submit" id="discount-submit" class="btn-gold text-sm px-5 py-2.5 whitespace-nowrap">
                        {{ __('home.descuento.submit') }}
                    </button>
                </form>
                <p id="discount-error" class="hidden mt-2 text-red-400 text-xs text-left"></p>
                <p class="mt-2 text-white/30 text-xs">{{ __('home.descuento.privacy') }}</p>
            </div>

            <div id="discount-success" class="hidden py-2">
                <span class="text-gold font-semibold text-sm">✓ </span>
                <span id="discount-success-msg" class="text-white/70 text-sm"></span>
            </div>
        </div>
    </div>

    <!-- Scroll Indicator -->
    <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 z-10 animate-bounce">
        <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
        </svg>
    </div>
</section>

<!-- SECTION 2: Diagnóstico Gratuito -->
<section id="diagnostico" class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <!-- Imagen (izquierda) -->
            <div class="hidden lg:block aspect-square bg-[#1A1A1A] overflow-hidden rounded">
                <img src="https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=800&q=80" alt="Consulta personalizada - Peluquería Jenver" class="w-full h-full object-cover" loading="lazy">
            </div>

            <!-- Contenido (derecha) -->
            <div>
                <h2 class="section-title mb-6">Diagnóstico Gratuito Personalizado</h2>

                <p class="text-gray-400 text-lg leading-relaxed mb-8">
                    Antes de cualquier servicio, realizamos un diagnóstico profesional gratuito para conocer exactamente el estado de tu cabello y tus objetivos.
                </p>

                <!-- Qué incluye -->
                <div class="space-y-4 mb-8">
                    <h3 class="text-gold font-bold text-lg mb-4">¿Qué incluye?</h3>

                    <div class="flex gap-4">
                        <div class="text-gold text-2xl flex-shrink-0">✓</div>
                        <div>
                            <h4 class="text-white font-semibold mb-1">Análisis del estado del cabello</h4>
                            <p class="text-gray-400 text-sm">Evaluamos salud, textura, daño y tipo de cabello.</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="text-gold text-2xl flex-shrink-0">✓</div>
                        <div>
                            <h4 class="text-white font-semibold mb-1">Consulta sobre tus objetivos</h4>
                            <p class="text-gray-400 text-sm">Escuchamos qué deseas lograr y qué buscas.</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="text-gold text-2xl flex-shrink-0">✓</div>
                        <div>
                            <h4 class="text-white font-semibold mb-1">Recomendación profesional</h4>
                            <p class="text-gray-400 text-sm">Te asesoramos sobre qué servicio te favorecerá más.</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="text-gold text-2xl flex-shrink-0">✓</div>
                        <div>
                            <h4 class="text-white font-semibold mb-1">Plan de cuidados personalizado</h4>
                            <p class="text-gray-400 text-sm">Te damos recomendaciones de cuidado específicas para ti.</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="text-gold text-2xl flex-shrink-0">✓</div>
                        <div>
                            <h4 class="text-white font-semibold mb-1">Sin compromiso</h4>
                            <p class="text-gray-400 text-sm">100% gratuito y sin obligación de contratar servicios.</p>
                        </div>
                    </div>
                </div>

                <!-- CTA -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-6 py-3">
                        Solicitar Diagnóstico
                    </a>
                    <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20un%20diagnóstico%20gratuito%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-6 py-3">
                        WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 3: Servicios Intro -->
<section id="servicios" class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-16">
            <p class="text-gold text-sm font-semibold mb-4 tracking-widest uppercase">{{ __('home.servicios_intro.title') }}</p>
            <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">{{ __('home.servicios_intro.subtitle') }}</h2>
            <p class="text-gray-400 max-w-2xl mx-auto text-lg">{{ __('home.servicios_intro.description') }}</p>
        </div>

        <!-- 4 Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
            @foreach(__('home.servicios_intro.cards') as $key => $card)
            <a href="{{ $card['url'] }}" class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8 group block">
                <div class="flex items-start gap-4 mb-4">
                    <div class="text-3xl">{{ $card['icon'] }}</div>
                    @if(isset($card['label']))
                    <span class="text-gold text-xs font-bold mt-1 bg-gold/10 px-2 py-1 rounded text-nowrap">{{ $card['label'] }}</span>
                    @endif
                </div>

                <h3 class="font-serif text-xl font-bold text-white mb-2 group-hover:text-gold transition-colors">{{ $card['title'] }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed mb-6">{{ $card['description'] }}</p>

                <!-- Services List -->
                <ul class="space-y-2">
                    @foreach($card['services'] as $service)
                    <li class="text-gray-300 text-sm flex items-start gap-2">
                        <span class="text-gold mt-1">•</span>
                        <span>{{ $service }}</span>
                    </li>
                    @endforeach
                </ul>
            </a>
            @endforeach
        </div>
    </div>
</section>

<!-- SECTION 4: Galería -->
<section id="galeria" class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="section-title">{{ __('home.galeria.title') }}</h2>
            <p class="text-gray-400">{{ __('home.galeria.subtitle') }}</p>
        </div>

        <!-- Image Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mb-8">
            <div class="aspect-square overflow-hidden bg-[#1A1A1A]">
                <img src="https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=800&q=80" alt="Balayage - Peluquería Jenver" class="w-full h-full object-cover hover:opacity-80 transition-opacity cursor-pointer" loading="lazy">
            </div>
            <div class="aspect-square overflow-hidden bg-[#1A1A1A]">
                <img src="https://images.unsplash.com/photo-1605497788044-5a32c7078486?w=800&q=80" alt="Técnicas de color - Peluquería Jenver" class="w-full h-full object-cover hover:opacity-80 transition-opacity cursor-pointer" loading="lazy">
            </div>
            <div class="aspect-square overflow-hidden bg-[#1A1A1A]">
                <img src="https://images.unsplash.com/photo-1573166475912-1ed8b4f093d2?w=800&q=80" alt="Cabello afro y rizado - Peluquería Jenver" class="w-full h-full object-cover hover:opacity-80 transition-opacity cursor-pointer" loading="lazy">
            </div>
            <div class="aspect-square overflow-hidden bg-[#1A1A1A]">
                <img src="https://images.unsplash.com/photo-1522338242992-e1a54906a8da?w=800&q=80" alt="Peinado de rizos - Peluquería Jenver" class="w-full h-full object-cover hover:opacity-80 transition-opacity cursor-pointer" loading="lazy">
            </div>
            <div class="aspect-square overflow-hidden bg-[#1A1A1A]">
                <img src="https://images.unsplash.com/photo-1634449571010-02389ed0f9b0?w=800&q=80" alt="Corte y estilismo - Peluquería Jenver" class="w-full h-full object-cover hover:opacity-80 transition-opacity cursor-pointer" loading="lazy">
            </div>
            <div class="aspect-square overflow-hidden bg-[#1A1A1A]">
                <img src="https://images.unsplash.com/photo-1559599101-f09722fb4948?w=800&q=80" alt="Corte profesional - Peluquería Jenver" class="w-full h-full object-cover hover:opacity-80 transition-opacity cursor-pointer" loading="lazy">
            </div>
        </div>

        <!-- Instagram Link -->
        <div class="text-center">
            <a href="https://www.instagram.com/peluqueriajenver/" target="_blank" rel="noopener noreferrer" class="btn-outline inline-block text-sm md:text-base">
                {{ __('home.galeria.cta') }}
            </a>
        </div>
    </div>
</section>

<!-- SECTION 5: Por qué elegirnos -->
<section id="nosotros" class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('home.nosotros.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
            <div class="text-center">
                <div class="text-gold font-serif text-5xl font-bold mb-2">{{ __('home.nosotros.stats.experience.number') }}</div>
                <p class="text-gray-300 font-semibold">{{ __('home.nosotros.stats.experience.label') }}</p>
            </div>
            <div class="text-center">
                <div class="text-gold font-serif text-5xl font-bold mb-2">{{ __('home.nosotros.stats.unisex.number') }}</div>
                <p class="text-gray-300 font-semibold">{{ __('home.nosotros.stats.unisex.label') }}</p>
            </div>
            <div class="text-center">
                <div class="text-gold font-serif text-5xl font-bold mb-2">{{ __('home.nosotros.stats.numero_uno.number') }}</div>
                <p class="text-gray-300 font-semibold">{{ __('home.nosotros.stats.numero_uno.label') }}</p>
            </div>
            <div class="text-center">
                <div class="text-gold font-serif text-5xl font-bold mb-2">{{ __('home.nosotros.stats.rating.number') }}</div>
                <p class="text-gray-300 font-semibold">{{ __('home.nosotros.stats.rating.label') }}</p>
            </div>
        </div>

        <div class="max-w-2xl mx-auto text-center">
            <p class="text-gray-400 leading-relaxed">
                {{ __('home.nosotros.description') }}
            </p>
        </div>
    </div>
</section>

<!-- SECTION 6: Reseñas -->
<section id="resenas" class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">{{ __('home.resenas.title') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Review 1 -->
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6">
                <div class="flex gap-1 mb-4">
                    <span class="text-gold">★</span>
                    <span class="text-gold">★</span>
                    <span class="text-gold">★</span>
                    <span class="text-gold">★</span>
                    <span class="text-gold">★</span>
                </div>
                <p class="text-gray-300 mb-4 italic">
                    "El mejor balayage que me han hecho nunca. Vine desde Barcelona norte porque me lo recomendaron y repetiré sin duda. Ambiente muy acogedor."
                </p>
                <p class="text-gray-500 text-sm font-semibold">— María G.</p>
            </div>

            <!-- Review 2 -->
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6">
                <div class="flex gap-1 mb-4">
                    <span class="text-gold">★</span>
                    <span class="text-gold">★</span>
                    <span class="text-gold">★</span>
                    <span class="text-gold">★</span>
                    <span class="text-gold">★</span>
                </div>
                <p class="text-gray-300 mb-4 italic">
                    "Especialistas en cabello afro de verdad. Llevan años cuidando mi pelo y siempre salen perfectos los peinados. Los recomiendo a todo el mundo."
                </p>
                <p class="text-gray-500 text-sm font-semibold">— Aminata K.</p>
            </div>

            <!-- Review 3 -->
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6">
                <div class="flex gap-1 mb-4">
                    <span class="text-gold">★</span>
                    <span class="text-gold">★</span>
                    <span class="text-gold">★</span>
                    <span class="text-gold">★</span>
                    <span class="text-gold">★</span>
                </div>
                <p class="text-gray-300 mb-4 italic">
                    "Fui a por un corte y me quedé con el color también. El trato es increíble y el resultado espectacular. Sin duda la mejor peluquería de Montcada i Reixac."
                </p>
                <p class="text-gray-500 text-sm font-semibold">— Laura M.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 7: CTA Reserva -->
<section id="reserva" class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <p class="text-gold text-xs font-semibold tracking-widest uppercase mb-4">{{ __('home.reserva.subtitle') }}</p>
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            {!! __('home.reserva.title') !!}
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            {!! __('home.reserva.description') !!}
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                {{ __('home.reserva.phone_cta') }}
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Quería%20pedir%20cita%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                {{ __('home.reserva.whatsapp_cta') }}
            </a>
        </div>
    </div>
</section>

<!-- SECTION 8: Contacto y Mapa -->
<section id="contacto" class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title mb-12">{{ __('home.contacto.title') }}</h2>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Contact Info (Left) -->
            <div>
                <h3 class="font-serif text-2xl font-bold text-white mb-6">{{ __('home.contacto.business_name') }}</h3>

                <div class="space-y-6 text-gray-300">
                    <div>
                        <p class="text-gold font-semibold mb-2">📍 {{ __('home.contacto.address_label') }}</p>
                        <p class="leading-relaxed">
                            {{ __('home.contacto.address_street') }}<br>
                            {{ __('home.contacto.address_postal') }}<br>
                            {{ __('home.contacto.address_city') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-gold font-semibold mb-2">📞 {{ __('home.contacto.phone_label') }}</p>
                        <a href="tel:+34633912050" class="hover:text-gold transition-colors">633 912 050</a>
                    </div>

                    <div>
                        <p class="text-gold font-semibold mb-2">🕐 {{ __('home.contacto.hours_label') }}</p>
                        <p class="leading-relaxed">
                            {!! __('home.contacto.hours') !!}
                        </p>
                    </div>

                    <a href="https://maps.google.com/?q=Peluquería+Jenver+Carrer+Lleida+21+Montcada+i+Reixac" target="_blank" rel="noopener noreferrer" class="inline-block text-gold hover:text-gold-light transition-colors font-semibold mt-2">
                        {{ __('home.contacto.maps_link') }}
                    </a>
                </div>
            </div>

            <!-- Map (Right) -->
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] overflow-hidden">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2990.3206850357657!2d2.1876543!3d41.489721!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x12a4a74d5b5b5b5d%3A0x5b5b5b5b5b5b5b5b!2sCarrer%20Lleida%2C%2021%2C%2008110%20Montcada%20i%20Reixac%2C%20Barcelona!5e0!3m2!1ses!2ses!4v1234567890"
                    width="100%"
                    height="350"
                    style="border:0; filter: grayscale(100%) invert(10%);"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </div>
</section>

@endsection
