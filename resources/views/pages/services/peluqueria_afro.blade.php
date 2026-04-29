@extends('layouts.app')

@section('title', 'Peluquería Afro y Cabello Rizado en Montcada i Reixac | Jenver')
@section('meta_description', 'Especialistas en cabello afro y rizado en Montcada i Reixac y Barcelona norte. Trenzas, twist, peinados naturales y tratamientos específicos. Llama al 633 912 050.')
@section('canonical', 'https://www.peluqueriajenver.com/peluqueria-afro')
@section('keywords', 'peluquería afro, cabello rizado, trenzas, box braids, método curly, Montcada i Reixac')

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1573166475912-1ed8b4f093d2?w=1920&q=80&fm=webp');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-block mb-6">
            <span class="text-gold text-xs font-semibold tracking-widest uppercase">✂ Especialistas en Texturas</span>
        </div>

        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            Especialistas en Cabello Afro y Rizado
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            Cuidamos y peinamos todo tipo de texturas con la técnica y el respeto que merecen.
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20agendar%20cita%20para%20peluquería%20afro%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base">
                WhatsApp →
            </a>
        </div>
    </div>

    <!-- Scroll Indicator -->
    <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 z-10 animate-bounce">
        <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
        </svg>
    </div>
</section>

<!-- SECTION 2: Nuestros Servicios -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Nuestros Servicios Afro</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <div class="text-4xl text-gold mb-4">✦</div>
                <h3 class="font-serif text-lg font-bold text-white mb-3">Trenzas y Box Braids</h3>
                <p class="text-gray-400 text-sm">Técnicas profesionales de trenzado que protegen tu cabello mientras lucen espectaculares.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <div class="text-4xl text-gold mb-4">◈</div>
                <h3 class="font-serif text-lg font-bold text-white mb-3">Twist y Twist Out</h3>
                <p class="text-gray-400 text-sm">Peinados naturales que resaltan la belleza de tu textura rizada de forma elegante.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <div class="text-4xl text-gold mb-4">🌀</div>
                <h3 class="font-serif text-lg font-bold text-white mb-3">Peinados Naturales Afro</h3>
                <p class="text-gray-400 text-sm">Afro puffs, buns y estilos que celebran tu cabello natural sin productos dañinos.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <div class="text-4xl text-gold mb-4">💧</div>
                <h3 class="font-serif text-lg font-bold text-white mb-3">Tratamientos Hidratantes</h3>
                <p class="text-gray-400 text-sm">Mascarillas profundas y tratamientos específicos para mantener tu cabello hidratado y saludable.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <div class="text-4xl text-gold mb-4">✂</div>
                <h3 class="font-serif text-lg font-bold text-white mb-3">Corte en Seco para Rizos</h3>
                <p class="text-gray-400 text-sm">Técnica especializada de corte en cabello seco para mantener la forma natural de tus rizos.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-6 rounded">
                <div class="text-4xl text-gold mb-4">🌊</div>
                <h3 class="font-serif text-lg font-bold text-white mb-3">Método Curly</h3>
                <p class="text-gray-400 text-sm">Definición de rizos natural con productos sin siliconas ni sulfatos para máxima salud capilar.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 3: Zona de Atención -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="section-title mb-8">Atendemos a toda la zona norte de Barcelona</h2>
        <p class="text-gray-300 text-lg leading-relaxed mb-6">
            Atendemos a clientas de Montcada i Reixac, Ripollet, Cerdanyola del Vallès, Santa Coloma de Gramenet y toda la zona norte de Barcelona. Si buscas una peluquería que de verdad entienda tu tipo de cabello, estás en el sitio correcto.
        </p>
    </div>
</section>

<!-- SECTION 4: Precios Orientativos -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Tarifas Orientativas</h2>
        @include('partials.precios', ['precios' => [
            ['Trenzas y box braids', 'desde 60€'],
            ['Twist y twist out', 'desde 50€'],
            ['Peinados naturales afro', 'desde 30€'],
            ['Tratamientos hidratantes', 'desde 40€'],
            ['Corte en seco para rizos', 'desde 25€'],
            ['Método curly (definición)', 'desde 45€'],
        ]])
    </div>
</section>

<!-- SECTION 5: CTA Final -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1573166475912-1ed8b4f093d2?w=1920&q=80&fm=webp');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Tu cabello se merece especialistas
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Reserva tu cita y descubre el cuidado profesional que tu cabello afro y rizado necesita.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20agendar%20cita%20para%20peluquería%20afro%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
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
  "name": "Peluquería Afro y Cabello Rizado",
  "description": "Especialistas en trenzas, twist, peinados naturales afro y método curly en Montcada i Reixac.",
  "provider": {
    "@type": "LocalBusiness",
    "name": "Peluquería Jenver",
    "url": "{{ url('/') }}"
  },
  "areaServed": [
    {
      "@type": "City",
      "name": "Montcada i Reixac"
    },
    {
      "@type": "City",
      "name": "Ripollet"
    },
    {
      "@type": "City",
      "name": "Cerdanyola del Vallès"
    },
    {
      "@type": "City",
      "name": "Santa Coloma de Gramenet"
    }
  ],
  "priceRange": "€€",
  "url": "{{ route('peluqueria-afro') }}"
}
</script>
@endpush

@endsection
