@extends('layouts.app')

@section('title', 'Manicura, Pedicura y Estética en Montcada i Reixac | Peluquería Jenver')
@section('meta_description', 'Manicura, pedicura, diseño de cejas y depilación facial en Peluquería Jenver, Montcada i Reixac. Todo en un solo salón.')
@section('canonical', 'https://www.peluqueriajenver.com/belleza-estetica')
@section('keywords', 'manicura, pedicura, diseño cejas, depilación facial, belleza integral, Montcada i Reixac')

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1552852081-a43eab14e5a5?w=1920&q=80&fm=webp');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-block mb-6">
            <span class="text-gold text-xs font-semibold tracking-widest uppercase">💅 Belleza integral</span>
        </div>

        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            Belleza y Estética
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            Completa tu transformación con nuestros servicios de belleza. Manicura, pedicura, cejas y depilación en un solo lugar.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                Reservar cita →
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20agendar%20cita%20para%20un%20servicio%20de%20belleza%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base">
                WhatsApp
            </a>
        </div>
    </div>
</section>

<!-- SECTION 2: Servicios incluidos -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Nuestros servicios de belleza</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
            @php
            $servicios = [
                ['nombre' => 'Manicura', 'desc' => 'Cuidado profesional de manos. Desde manicura clásica hasta diseños personalizados y decoraciones.', 'icon' => '💅'],
                ['nombre' => 'Pedicura', 'desc' => 'Pedicura completa con hidratación profunda. Pies cuidados, suaves y hermosos.', 'icon' => '🦶'],
                ['nombre' => 'Diseño de Cejas', 'desc' => 'Depilación y diseño de cejas personalizadas. El marco perfecto para tu rostro.', 'icon' => '✨'],
                ['nombre' => 'Depilación Facial', 'desc' => 'Depilación profesional de cara, bigote y vello corporal. Métodos suaves y efectivos.', 'icon' => '🪮'],
            ];
            @endphp

            @foreach($servicios as $servicio)
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8 rounded">
                <div class="text-4xl text-gold mb-4">{{ $servicio['icon'] }}</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">{{ $servicio['nombre'] }}</h3>
                <p class="text-gray-400 text-sm leading-relaxed">{{ $servicio['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- SECTION 3: Los detalles hacen la diferencia -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-8">Los detalles hacen la diferencia</h2>
        <p class="text-gray-400 text-lg leading-relaxed text-center mb-12">
            En Jenver sabemos que la belleza está en los detalles. Por eso ofrecemos servicios complementarios que perfeccionen tu look completo. Todos nuestros servicios se realizan con higiene profesional y productos de calidad.
        </p>
    </div>
</section>

<!-- SECTION 4: Por qué elegirnos -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">¿Por qué Peluquería Jenver?</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">✨</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Higiene Profesional</h3>
                <p class="text-gray-400">Cumplimos con los más altos estándares de higiene y desinfección en todos nuestros servicios.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">💎</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Productos Premium</h3>
                <p class="text-gray-400">Usamos únicamente productos de calidad profesional que cuidan tu piel y uñas.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">👥</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Especialistas</h3>
                <p class="text-gray-400">Nuestros profesionales tienen formación específica en cada servicio de belleza.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">🎁</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Belleza Integral</h3>
                <p class="text-gray-400">Todos los servicios que necesitas en un solo lugar. Cabeza a pies, todo cuidado.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 5: Promociones especiales -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Paquetes y promociones</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 text-center rounded">
                <h3 class="font-serif text-xl font-bold text-white mb-4">Manicura + Pedicura</h3>
                <p class="text-gray-400 mb-4">Combo de cuidado completo para manos y pies.</p>
                <p class="text-gold text-sm">Consúltanos precios especiales</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 text-center rounded">
                <h3 class="font-serif text-xl font-bold text-white mb-4">Cejas + Depilación</h3>
                <p class="text-gray-400 mb-4">Cuidado completo del rostro en una sesión.</p>
                <p class="text-gold text-sm">Consúltanos precios especiales</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 text-center rounded">
                <h3 class="font-serif text-xl font-bold text-white mb-4">Belleza Completa</h3>
                <p class="text-gray-400 mb-4">Manicura, pedicura, cejas y depilación.</p>
                <p class="text-gold text-sm">Consúltanos precios especiales</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 6: CTA Final -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1604654894610-df63bc536371?w=1920&q=80&fm=webp');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Los detalles hacen la diferencia
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Completa tu transformación con nuestros servicios de belleza complementarios.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20agendar%20cita%20para%20un%20servicio%20de%20belleza%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                WhatsApp →
            </a>
        </div>
    </div>
</section>

@push('structured_data')
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Service",
  "name": "Belleza y Estética",
  "description": "Manicura, pedicura, diseño de cejas y depilación facial.",
  "provider": {
    "@type": "LocalBusiness",
    "name": "Peluquería Jenver",
    "url": "{{ url('/') }}"
  },
  "areaServed": {
    "@type": "City",
    "name": "Montcada i Reixac"
  },
  "priceRange": "€€",
  "url": "{{ route('belleza-estetica') }}"
}
</script>
@endpush

@endsection
