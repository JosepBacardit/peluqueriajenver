@extends('layouts.app')

@section('title', 'Corte de Pelo y Keratina en Montcada i Reixac | Peluquería Jenver')
@section('meta_description', 'Corte de mujer, caballero y niños. Keratina y alisados duraderos. Peluquería Jenver, Montcada i Reixac. Llama o escríbenos por WhatsApp.')
@section('canonical', 'https://www.peluqueriajenver.com/corte-y-tratamientos')
@section('keywords', 'corte cabello, alisado, keratina, corte caballeros, barbería, Montcada i Reixac')

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('{{ asset('images/corte-y-tratamientos.png') }}');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-block mb-6">
            <span class="text-gold text-xs font-semibold tracking-widest uppercase">✂ Técnica profesional</span>
        </div>

        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            Corte y Tratamientos
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            Un buen corte cambia tu look. Alisados duraderos, keratina profesional y barbería de calidad para toda la familia.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                Reservar cita →
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20agendar%20cita%20para%20corte%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base">
                WhatsApp
            </a>
        </div>
    </div>
</section>

<!-- SECTION 2: Servicios incluidos -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Nuestros servicios de corte y tratamientos</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            @php
            $servicios = [
                ['nombre' => 'Corte de Mujer', 'desc' => 'Cortes modernos, capas, flequillos. Personalizados a tu tipo de cabello y cara.', 'icon' => '💇‍♀️'],
                ['nombre' => 'Corte Caballeros', 'desc' => 'Barbería tradicional, perfilado y máquina. Técnicas clásicas y modernas.', 'icon' => '💇‍♂️'],
                ['nombre' => 'Corte Infantil', 'desc' => 'Cortes cómodos y fáciles de mantener para niños y niñas.', 'icon' => '👶'],
                ['nombre' => 'Alisado Permanente', 'desc' => 'Alisados duraderos que protegen tu cabello mientras lo transforman.', 'icon' => '✨'],
                ['nombre' => 'Keratina', 'desc' => 'Tratamiento que repara, hidrata y alisa progresivamente. Resultados de 3-4 meses.', 'icon' => '💪'],
                ['nombre' => 'Arreglo de Barba', 'desc' => 'Perfilado y diseño profesional de barba. Acabado impecable.', 'icon' => '🎯'],
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

<!-- SECTION 3: Proceso -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Nuestro proceso</h2>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            @php
            $pasos = [
                'Consulta sobre tu estilo',
                'Análisis de la forma de tu cara',
                'Propuesta y acuerdo del corte',
                'Corte profesional con técnica experta',
                'Consejos de cuidado y mantenimiento',
            ];
            @endphp

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

<!-- SECTION 4: Por qué elegirnos -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">¿Por qué Peluquería Jenver?</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">✂️</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Técnica Profesional</h3>
                <p class="text-gray-400">Barberos y estilistas con formación especializada en todas las técnicas.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">👤</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Personalizado</h3>
                <p class="text-gray-400">Cada corte se adapta a tu forma de cara, tipo de cabello y estilo personal.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">🧑‍🤝‍🧑</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Para Toda la Familia</h3>
                <p class="text-gray-400">Especialistas para mujer, caballero, niños y barba. Todos encuentran su especialista.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">🏥</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Tratamientos Profesionales</h3>
                <p class="text-gray-400">Alisados y keratina que protegen tu cabello mientras lo transforman.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 5: CTA Final -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('{{ asset('images/salon-belleza-moderno-elegante.png') }}');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Un corte profesional cambia tu look
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Reserva tu cita y descubre la diferencia de un corte y tratamiento profesional.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20agendar%20cita%20para%20corte%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
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
  "name": "Corte y Tratamientos",
  "description": "Corte de mujer, caballero y niños. Keratina y alisado permanente profesional.",
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
  "url": "{{ route('corte-tratamientos') }}",
  "image": "{{ asset('images/corte-y-tratamientos.png') }}"
}
</script>
@endpush

@endsection
