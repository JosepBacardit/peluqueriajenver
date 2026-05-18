@extends('layouts.app')

@section('robots', 'noindex, follow')
@section('title', 'Contacto - Peluquería Jenver')
@section('meta_description', 'Contacta con Peluquería Jenver en Montcada i Reixac. Llamadas, WhatsApp o visita nuestro local. Especialistas en balayage, cabello afro y rizos.')
@section('canonical', 'https://www.peluqueriajenver.com/contacto')

@section('content')

<!-- Hero Section -->
<section class="relative py-16 md:py-24 bg-[#111111]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h1 class="font-serif text-4xl md:text-5xl font-bold text-white mb-4">{{ __('home.contacto.title') }}</h1>
            <p class="text-gray-400 text-lg max-w-2xl mx-auto">Nos encantaría escucharte. Contacta con nosotros de la forma que más cómoda te resulte.</p>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Contact Info (Left) -->
            <div>
                <h2 class="font-serif text-2xl font-bold text-white mb-6">{{ __('home.contacto.business_name') }}</h2>

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
                        <a href="tel:+34633912050" class="hover:text-gold transition-colors text-lg font-semibold">633 912 050</a>
                    </div>

                    <div>
                        <p class="text-gold font-semibold mb-2">🕐 {{ __('home.contacto.hours_label') }}</p>
                        <p class="leading-relaxed">
                            {!! __('home.contacto.hours') !!}
                        </p>
                    </div>

                    <a href="https://maps.google.com/?q=Peluquería+Jenver+Carrer+Lleida+21+Montcada+i+Reixac" target="_blank" rel="noopener noreferrer" class="inline-block text-gold hover:text-gold-light transition-colors font-semibold mt-4">
                        {{ __('home.contacto.maps_link') }}
                    </a>
                </div>

                <!-- CTA Buttons -->
                <div class="flex flex-col gap-3 mt-8">
                    <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-6 py-3 text-center">
                        📞 Llamar ahora
                    </a>
                    <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20contactar%20con%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-6 py-3 text-center">
                        💬 WhatsApp
                    </a>
                </div>
            </div>

            <!-- Map (Right) -->
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] overflow-hidden rounded">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2990.3206850357657!2d2.1876543!3d41.489721!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x12a4a74d5b5b5b5d%3A0x5b5b5b5b5b5b5b5b!2sCarrer%20Lleida%2C%2021%2C%2008110%20Montcada%20i%20Reixac%2C%20Barcelona!5e0!3m2!1ses!2ses!4v1234567890"
                    width="100%"
                    height="400"
                    style="border:0; filter: grayscale(100%) invert(10%);"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </div>
</section>

<!-- Formulario de contacto rápido -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-8">¿Prefieres que te contactemos?</h2>

        <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
            <p class="text-gray-400 text-center mb-6">Si lo prefieres, déjanos tu información y nos pondremos en contacto contigo lo antes posible.</p>

            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-white font-semibold mb-2">Nombre</label>
                    <input type="text" id="name" placeholder="Tu nombre" class="w-full bg-[#2A2A2A] border border-[#3A3A3A] text-white px-4 py-2 rounded focus:outline-none focus:border-gold">
                </div>

                <div>
                    <label for="phone" class="block text-white font-semibold mb-2">Teléfono</label>
                    <input type="tel" id="phone" placeholder="Tu teléfono" class="w-full bg-[#2A2A2A] border border-[#3A3A3A] text-white px-4 py-2 rounded focus:outline-none focus:border-gold">
                </div>

                <div>
                    <label for="email" class="block text-white font-semibold mb-2">Email (opcional)</label>
                    <input type="email" id="email" placeholder="Tu email" class="w-full bg-[#2A2A2A] border border-[#3A3A3A] text-white px-4 py-2 rounded focus:outline-none focus:border-gold">
                </div>

                <div>
                    <label for="message" class="block text-white font-semibold mb-2">Mensaje</label>
                    <textarea id="message" placeholder="Tu mensaje..." rows="4" class="w-full bg-[#2A2A2A] border border-[#3A3A3A] text-white px-4 py-2 rounded focus:outline-none focus:border-gold"></textarea>
                </div>

                <button type="submit" class="btn-gold w-full text-sm md:text-base font-semibold py-3">
                    Enviar mensaje
                </button>
            </div>
        </div>
    </div>
</section>

@endsection
