@extends('layouts.app')

@section('title', 'Sobre Nosotros | Peluquería Jenver')
@section('description', 'Conoce la historia de Peluquería Jenver. Especialistas en balayage, cabello afro, rizos y cuidado capilar desde 2015 en Montcada i Reixac.')
@section('keywords', 'sobre nosotros, historia peluquería, equipo Jenver, especialistas balayage, peluquería Montcada i Reixac')

@section('content')

<!-- SECTION 1: Hero -->
<section class="relative w-full h-screen md:h-[90vh] bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/60"></div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-block mb-6">
            <span class="text-gold text-xs font-semibold tracking-widest uppercase">✂ Nuestra historia</span>
        </div>

        <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
            Sobre Peluquería Jenver
        </h1>

        <p class="text-gray-300 text-lg md:text-xl mb-8 leading-relaxed">
            Más de 10 años transformando looks y cuidando cabellos con técnica, pasión y atención personalizada.
        </p>
    </div>
</section>

<!-- SECTION 2: Nuestra Historia -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <!-- Imagen -->
            <div class="hidden lg:block aspect-square bg-[#1A1A1A] overflow-hidden rounded">
                <img src="https://images.unsplash.com/photo-1533621821729-239d1fda76ab?w=800&q=80" alt="Peluquería Jenver - Equipo profesional" class="w-full h-full object-cover" loading="lazy">
            </div>

            <!-- Contenido -->
            <div>
                <h2 class="section-title mb-6">Nuestra Historia</h2>
                <p class="text-gray-400 text-lg leading-relaxed mb-6">
                    Peluquería Jenver nació en 2015 con una misión clara: ser la peluquería de referencia en Montcada i Reixac y la zona norte de Barcelona.
                </p>
                <p class="text-gray-400 text-lg leading-relaxed mb-6">
                    Lo que comenzó como un pequeño salón unisex se convirtió en el lugar donde especialistas en color, balayage, cabello afro y rizos trabajan cada día para transformar looks y cuidar cabellos.
                </p>
                <p class="text-gray-400 text-lg leading-relaxed mb-6">
                    Nuestro compromiso es sencillo: atención personalizada, técnica experta, resultados que te encanten y un ambiente donde te sientas como en casa.
                </p>
                <a href="tel:+34633912050" class="btn-gold text-sm md:text-base inline-block">
                    Reservar cita →
                </a>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 3: Nuestros Valores -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Nuestros Valores</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8 rounded text-center">
                <div class="text-5xl text-gold mb-4">✓</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Calidad</h3>
                <p class="text-gray-400 text-sm">Usamos solo productos y técnicas profesionales de la más alta calidad para tus cabellos.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8 rounded text-center">
                <div class="text-5xl text-gold mb-4">❤️</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Pasión</h3>
                <p class="text-gray-400 text-sm">Amamos lo que hacemos y eso se refleja en cada corte, color y tratamiento que realizamos.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8 rounded text-center">
                <div class="text-5xl text-gold mb-4">👤</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Personalización</h3>
                <p class="text-gray-400 text-sm">Cada persona es única. Nos tomamos tiempo para conocer tus necesidades y gustos.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8 rounded text-center">
                <div class="text-5xl text-gold mb-4">🏠</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Comunidad</h3>
                <p class="text-gray-400 text-sm">Queremos ser parte de tu vida y de tu rutina de autocuidado y belleza.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 4: Especialidades del Equipo -->
<section class="bg-[#0A0A0A] py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">Nuestras Especialidades</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">✦</div>
                <h3 class="font-serif text-2xl font-bold text-white mb-4">Color y Mechas</h3>
                <p class="text-gray-400 mb-4">Expertos en balayage, babylights, coloración completa y retoque de raíz. Creamos resultados naturales o de impacto según lo que busques.</p>
                <ul class="text-gray-400 text-sm space-y-2">
                    <li>✓ Balayage y degradados</li>
                    <li>✓ Mechas clásicas y modernas</li>
                    <li>✓ Coloración personalizada</li>
                    <li>✓ Retoque de raíz profesional</li>
                </ul>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">◈</div>
                <h3 class="font-serif text-2xl font-bold text-white mb-4">Cabello Afro y Rizos</h3>
                <p class="text-gray-400 mb-4">Especialistas en todo tipo de texturas. Peinados, definición, cuidado y tratamientos específicos para cabello afro y rizado.</p>
                <ul class="text-gray-400 text-sm space-y-2">
                    <li>✓ Peinado cabello afro</li>
                    <li>✓ Definición de rizos</li>
                    <li>✓ Tratamientos protectores</li>
                    <li>✓ Asesoramiento y cuidados</li>
                </ul>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">✂</div>
                <h3 class="font-serif text-2xl font-bold text-white mb-4">Corte y Estilismo</h3>
                <p class="text-gray-400 mb-4">Cortes para hombre, mujer y niños adaptados a tu forma de cara y estilo. Extensiones y tratamientos capilares.</p>
                <ul class="text-gray-400 text-sm space-y-2">
                    <li>✓ Corte personalizado</li>
                    <li>✓ Extensiones de cabello</li>
                    <li>✓ Alisado y keratina</li>
                    <li>✓ Arreglo de barba profesional</li>
                </ul>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">👑</div>
                <h3 class="font-serif text-2xl font-bold text-white mb-4">Peinados para Eventos</h3>
                <p class="text-gray-400 mb-4">Diseños exclusivos para bodas, eventos y momentos especiales. Con prueba previa incluida y garantía de duración.</p>
                <ul class="text-gray-400 text-sm space-y-2">
                    <li>✓ Peinado para bodas</li>
                    <li>✓ Peinados para eventos</li>
                    <li>✓ Recogidos y ondas</li>
                    <li>✓ Prueba previa incluida</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 5: Por qué elegirnos -->
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="section-title text-center mb-12">¿Por qué Peluquería Jenver?</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">10+</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Años de Experiencia</h3>
                <p class="text-gray-400">Un equipo con más de una década transformando cabellos y creando looks que duran.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">★★★★★</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Valoración en Google</h3>
                <p class="text-gray-400">Cientos de clientes satisfechos que regresan una y otra vez y nos recomiendan.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">👥</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Equipo Multidisciplinar</h3>
                <p class="text-gray-400">Especialistas en color, corte, cabello afro, rizos, eventos y servicios complementarios.</p>
            </div>

            <div class="bg-[#1A1A1A] border border-[#2A2A2A] p-8 rounded">
                <div class="text-4xl text-gold mb-4">📍</div>
                <h3 class="font-serif text-xl font-bold text-white mb-3">Ubicación Accesible</h3>
                <p class="text-gray-400">En el corazón de Montcada i Reixac, con fácil acceso desde Barcelona norte.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 6: CTA Final -->
<section class="relative py-20 md:py-32 bg-cover bg-center flex items-center justify-center" style="background-image: url('https://images.unsplash.com/photo-1560066984-138dadb4c035?w=1920&q=80');">
    <div class="absolute inset-0 bg-black/70"></div>

    <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">
            Únete a la Comunidad Jenver
        </h2>
        <p class="text-gray-300 text-lg mb-8">
            Reserva tu cita y descubre por qué somos la peluquería favorita de Montcada i Reixac y alrededores.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="tel:+34633912050" class="btn-gold text-sm md:text-base font-semibold px-8 py-3">
                Llamar: 633 912 050
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Me%20gustaría%20agendar%20cita%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-sm md:text-base font-semibold px-8 py-3">
                WhatsApp →
            </a>
        </div>
    </div>
</section>

@endsection
