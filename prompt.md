PROMPT PARA CLAUDE CODE — Web Peluquería Jenver
Laravel 13 + Blade + Tailwind CSS — Frontend completo con SEO local

CONTEXTO DEL PROYECTO
Crea el frontend completo de la web de Peluquería Jenver, un salón unisex ubicado en Carrer Lleida, 21, 08110 Montcada i Reixac, Barcelona. La web está construida con Laravel 13 usando Blade templates y Tailwind CSS (sin frameworks adicionales).
La web debe posicionarse localmente en Google para las siguientes palabras clave:
Keywords primarias (máxima prioridad):

peluquería Montcada i Reixac
peluquería unisex Montcada i Reixac
peluquería cabello afro Montcada i Reixac
balayage Montcada i Reixac
especialista rizos Montcada i Reixac

Keywords secundarias:

corte de pelo Montcada i Reixac
tinte pelo Montcada i Reixac
extensiones cabello Montcada i Reixac
peluquería cerca de Montcada i Reixac
peluquería afro Barcelona norte

Keywords de marca:

Jenver peluquería
peluquería Jenver Montcada


PALETA DE COLORES
Extraída del logo oficial (fondo negro, tipografía blanca, detalle en dorado/bronce cálido):
--color-black:    #0A0A0A   /* Fondo principal */
--color-dark:     #111111   /* Fondo secundario */
--color-card:     #1A1A1A   /* Tarjetas / superficies */
--color-gold:     #C9A84C   /* Dorado principal — acento primario */
--color-gold-light: #E2C97E /* Dorado claro — hover, gradientes */
--color-gold-dark:  #A07830 /* Dorado oscuro — activo */
--color-white:    #FFFFFF   /* Texto principal sobre negro */
--color-gray-300: #D1D1D1   /* Texto secundario */
--color-gray-500: #6B6B6B   /* Texto muted */
--color-border:   #2A2A2A   /* Bordes sutiles */
En Tailwind, extiende la config así:
js// tailwind.config.js
theme: {
  extend: {
    colors: {
      gold: {
        DEFAULT: '#C9A84C',
        light: '#E2C97E',
        dark: '#A07830',
      }
    },
    fontFamily: {
      serif: ['"Playfair Display"', 'Georgia', 'serif'],
      sans:  ['"Inter"', 'system-ui', 'sans-serif'],
    }
  }
}
Importar en el HTML del layout:
html<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
Estética general: Lujo accesible. Negro profundo como base, dorado como acento exclusivo. Espaciado generoso. Tipografía serif para títulos, sans-serif para cuerpo. Sin gradientes coloridos — solo negro, blanco y dorado.

FOTOS / IMÁGENES
Todas las imágenes deben cargarse desde Instagram de Peluquería Jenver usando la URL pública del CDN de Instagram. El perfil es @peluqueriajenver.
Usa estos src con imágenes de Unsplash como placeholder (instrucciones al final sobre cómo sustituirlas):
Para cada imagen usa un <img> con:

loading="lazy" siempre
alt descriptivo con keywords (ej: alt="Balayage en Montcada i Reixac - Peluquería Jenver")
Clases Tailwind para aspect-ratio consistente

Placeholders Unsplash por sección (usar estas URLs exactas):
Hero principal:
https://images.unsplash.com/photo-1560066984-138dadb4c035?w=1920&q=80
(salón de peluquería oscuro, ambiente elegante)

Balayage / color:
https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=800&q=80
https://images.unsplash.com/photo-1605497788044-5a32c7078486?w=800&q=80

Cabello afro / rizos:
https://images.unsplash.com/photo-1573166475912-1ed8b4f093d2?w=800&q=80
https://images.unsplash.com/photo-1522338242992-e1a54906a8da?w=800&q=80

Corte y estilismo:
https://images.unsplash.com/photo-1634449571010-02389ed0f9b0?w=800&q=80
https://images.unsplash.com/photo-1559599101-f09722fb4948?w=800&q=80

Equipo / salón:
https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=800&q=80

Nota para el desarrollador: Sustituye estas imágenes por las reales del Instagram @peluqueria_jenver. Puedes descargarlas manualmente y servirlas desde /public/images/ o usar el embed de Instagram en la sección de galería.


ESTRUCTURA DE ARCHIVOS A CREAR
resources/
└── views/
    ├── layouts/
    │   └── app.blade.php          ← Layout principal
    ├── partials/
    │   ├── header.blade.php       ← Navbar con logo
    │   ├── footer.blade.php       ← Footer completo
    │   └── schema-local.blade.php ← JSON-LD SEO local
    └── pages/
        ├── home.blade.php         ← Página principal (home)
        └── servicios.blade.php    ← Página de servicios (opcional, estructura base)

public/
└── images/
    └── logo-jenver.jpg            ← Logo ya disponible

ARCHIVO 1: layouts/app.blade.php
Layout base que deben extender todas las páginas.
Debe incluir:
html<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- SEO Meta tags dinámicos con @yield -->
  <title>@yield('title', 'Peluquería Jenver | Montcada i Reixac')</title>
  <meta name="description" content="@yield('description', 'Peluquería unisex en Montcada i Reixac. Especialistas en balayage, cabello afro y rizos. Corte, color y extensiones. Reserva tu cita: 633 912 050.')">
  <meta name="keywords" content="@yield('keywords', 'peluquería Montcada i Reixac, balayage, cabello afro, rizos, peluquería unisex')">

  <!-- Open Graph -->
  <meta property="og:title" content="@yield('og_title', 'Peluquería Jenver | Montcada i Reixac')">
  <meta property="og:description" content="@yield('og_description', 'Especialistas en balayage, cabello afro y rizos en Montcada i Reixac, Barcelona.')">
  <meta property="og:image" content="@yield('og_image', asset('images/logo-jenver.jpg'))">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="es_ES">

  <!-- Canonical -->
  <link rel="canonical" href="@yield('canonical', url()->current())">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- Tailwind + App CSS -->
  @vite(['resources/css/app.css', 'resources/js/app.js'])

  <!-- Schema JSON-LD SEO Local -->
  @include('partials.schema-local')

  @stack('head')
</head>
<body class="bg-[#0A0A0A] text-white font-sans antialiased">

  @include('partials.header')

  <main>
    @yield('content')
  </main>

  @include('partials.footer')

  @stack('scripts')
</body>
</html>

ARCHIVO 2: partials/header.blade.php
Navbar elegante, oscura, con el logo a la izquierda.
Comportamiento:

Fijo en la parte superior (fixed top-0 z-50)
Fondo: bg-black/90 backdrop-blur-md border-b border-[#2A2A2A]
Al hacer scroll, añadir clase scrolled con JS (sombra sutil)
En móvil: menú hamburguesa que despliega un menú vertical con fondo negro

Contenido del navbar:
Logo (imagen logo-jenver.jpg, height: 48px)    |    Inicio  Servicios  Sobre nosotros  Contacto    |    [Reservar cita →]
Botón CTA "Reservar cita":

Color dorado: bg-gold hover:bg-gold-light text-black font-semibold px-5 py-2 rounded-none (sin border-radius, estética luxury)
En móvil: visible y destacado en el menú desplegable

Links de navegación:

Color: text-gray-300 hover:text-gold transition-colors
El link activo: text-gold
Usar request()->routeIs() para detectar el activo

Anuncio banner opcional (encima del nav, muy fino):
Pequeña barra superior negra/dorada con: "📍 Carrer Lleida, 21, Montcada i Reixac · Tel: 633 912 050"

ARCHIVO 3: partials/footer.blade.php
Footer oscuro, elegante y completo con toda la info de negocio local (crítico para SEO local).
Estructura en 4 columnas (en desktop, 2 en tablet, 1 en móvil):
Col 1 — Logo + descripción:
  - Logo Jenver (versión pequeña)
  - Texto: "Peluquería unisex en Montcada i Reixac. Especialistas en color, balayage y cabello afro y rizado."
  - Iconos redes: Instagram (@peluqueria_jenver), TikTok

Col 2 — Servicios (con links a anclas de la home o a /servicios):
  - Balayage y mechas
  - Coloración capilar
  - Cabello afro y rizos
  - Corte de pelo
  - Extensiones
  - Tratamientos capilares

Col 3 — Información local (vital para SEO):
  - 📍 Carrer Lleida, 21 · 08110 Montcada i Reixac · Barcelona
  - 📞 633 912 050
  - 🕐 Mar-Sáb: 9:00 – 19:00 | Lun-Dom: Cerrado
  - Link a Google Maps (href con coordenadas exactas o Google Maps search)

Col 4 — Mapa embed o zona de texto SEO:
  - Iframe de Google Maps embebido (mapa pequeño del local)
  - O alternativamente: texto con keywords locales:
    "Atendemos clientes de Montcada i Reixac, La Ribera, Mas Rampinyó, Ripollet y alrededores."
Barra inferior del footer:
© 2025 Peluquería Jenver · Todos los derechos reservados     |     Política de privacidad · Aviso legal
Colores footer:

Fondo: bg-[#0D0D0D] o bg-black
Separador: línea dorada de 1px (border-t border-gold/30)
Títulos columnas: text-gold font-serif text-lg
Texto: text-gray-400
Links: text-gray-400 hover:text-gold transition-colors


ARCHIVO 4: partials/schema-local.blade.php
Schema JSON-LD para SEO local (LocalBusiness). Crítico para el Local Pack de Google.
html<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "HairSalon",
  "name": "Peluquería Jenver",
  "description": "Peluquería unisex en Montcada i Reixac especializada en balayage, cabello afro, rizos y coloración capilar.",
  "url": "{{ url('/') }}",
  "telephone": "+34633912050",
  "email": "",
  "image": "{{ asset('images/logo-jenver.jpg') }}",
  "logo": "{{ asset('images/logo-jenver.jpg') }}",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Carrer Lleida, 21",
    "addressLocality": "Montcada i Reixac",
    "addressRegion": "Barcelona",
    "postalCode": "08110",
    "addressCountry": "ES"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 41.4897,
    "longitude": 2.1898
  },
  "openingHoursSpecification": [
    { "@type": "OpeningHoursSpecification", "dayOfWeek": ["Tuesday","Wednesday","Thursday","Friday","Saturday"], "opens": "09:00", "closes": "19:00" },
    { "@type": "OpeningHoursSpecification", "dayOfWeek": ["Monday","Sunday"], "opens": "00:00", "closes": "00:00" }
  ],
  "priceRange": "€€",
  "currenciesAccepted": "EUR",
  "paymentAccepted": "Cash, Credit Card",
  "areaServed": ["Montcada i Reixac", "Ripollet", "Cerdanyola del Vallès", "Santa Coloma de Gramenet"],
  "hasMap": "https://maps.google.com/?q=Peluquería+Jenver+Carrer+Lleida+21+Montcada+i+Reixac",
  "sameAs": [
    "https://www.instagram.com/peluqueria_jenver/",
    "https://www.tiktok.com/@peluqueriajenver"
  ],
  "makesOffer": [
    { "@type": "Offer", "itemOffered": { "@type": "Service", "name": "Balayage Montcada i Reixac" }},
    { "@type": "Offer", "itemOffered": { "@type": "Service", "name": "Cabello afro Montcada i Reixac" }},
    { "@type": "Offer", "itemOffered": { "@type": "Service", "name": "Especialista rizos Montcada i Reixac" }},
    { "@type": "Offer", "itemOffered": { "@type": "Service", "name": "Corte de pelo Montcada i Reixac" }},
    { "@type": "Offer", "itemOffered": { "@type": "Service", "name": "Extensiones cabello Montcada i Reixac" }}
  ]
}
</script>

ARCHIVO 5: pages/home.blade.php
Página principal. Debe extender el layout y tener las siguientes secciones en este orden:
Meta SEO (al inicio del archivo):
blade@extends('layouts.app')

@section('title', 'Peluquería Jenver | Especialistas en Balayage, Afro y Rizos · Montcada i Reixac')
@section('description', 'Peluquería unisex en Montcada i Reixac. Especialistas en balayage, mechas, cabello afro, rizos y coloración. Corte de pelo hombre y mujer. Reserva: 633 912 050.')
@section('keywords', 'peluquería Montcada i Reixac, balayage Montcada i Reixac, cabello afro Montcada i Reixac, especialista rizos, peluquería unisex, corte de pelo Montcada')

SECCIÓN 1 — Hero (#hero)
Diseño: Full-screen o 90vh. Imagen de fondo oscura con overlay negro semitransparente.
[Imagen de fondo del salón — oscura, elegante]
[Overlay: bg-black/60]

  ↑ pequeño tag superior: "Montcada i Reixac, Barcelona"  (texto dorado, letra pequeña, tracking wide)

  H1 (Playfair Display, grande, blanco):
  "Tu mejor versión
   empieza aquí"

  Subtítulo (Inter, gris claro):
  "Especialistas en Color · Balayage · Cabello Afro y Rizado
   Peluquería unisex en Montcada i Reixac"

  [Botón primario dorado: "Reservar cita  →"]   [Botón secundario outline: "Ver servicios"]

  ↓ Scroll indicator animado (chevron dorado)
H1 nota SEO: El H1 debe contener las keywords principales. Usa el texto visible pero asegura que el <h1> incluya semánticamente "peluquería Montcada i Reixac".

SECCIÓN 2 — Especialidades destacadas (#especialidades)
Diseño: 3 tarjetas horizontales (o en grid 3 cols desktop, 1 col móvil). Fondo ligeramente más claro que el hero (bg-[#111111]).
Cada tarjeta tiene:

Icono SVG o emoji grande (dorado)
Título h3 en serif
Descripción breve con keyword incluida

Tarjeta 1:
  Icono: ✦ (estrella)
  Título: "Balayage & Color"
  Texto: "Especialistas en balayage, mechas y coloración capilar en Montcada i Reixac. Técnicas de degradado natural y color personalizado."

Tarjeta 2:
  Icono: ◈ (o icono de rizos)
  Título: "Cabello Afro y Rizos"
  Texto: "Los únicos especialistas en cabello afro y rizado de Montcada i Reixac. Peinados, definición y cuidado de todo tipo de texturas."

Tarjeta 3:
  Icono: ✂ (tijeras estilizadas)
  Título: "Corte & Estilismo"
  Texto: "Corte de pelo para hombre, mujer y niños en Montcada i Reixac. Extensiones y tratamientos capilares personalizados."
Estética tarjetas: bg-[#1A1A1A] border border-[#2A2A2A] hover:border-gold/50 transition-colors p-8 rounded-none (sin border-radius).

SECCIÓN 3 — Galería de trabajos (#galeria)
Diseño: Grid de imágenes tipo masonry o grid uniforme. 6-9 fotos. Título de sección centrado.
Título: "Nuestros trabajos"
Subtítulo: "Síguenos en Instagram @peluqueriajenver"
Grid: grid grid-cols-2 md:grid-cols-3 gap-2 — imágenes cuadradas con aspect-square object-cover.
Cada imagen tiene:

Overlay hover:opacity-80 transition-opacity
alt con keyword: "Balayage Montcada i Reixac", "Cabello afro Montcada i Reixac", etc.

Al final de la galería: botón link a Instagram:
[Ver más en Instagram →]  (color dorado, outline)
href: "https://www.instagram.com/peluqueriajenver/"  target="_blank"

SECCIÓN 4 — Servicios con precios (#servicios)
Diseño: 2 columnas. Izquierda: imagen. Derecha: lista de servicios.
Título sección: "Servicios"  (serif, dorado)

Lista de servicios (tabla o lista elegante con separadores):
  Corte de pelo mujer .................. Desde €20
  Corte de pelo hombre ................. Desde €15
  Corte infantil ........................ Desde €12
  Balayage .............................. Desde €60
  Mechas / Highlights ................... Desde €45
  Coloración completa ................... Desde €40
  Peinado cabello afro .................. Consultar
  Definición de rizos ................... Consultar
  Extensiones de cabello ................ Consultar
  Tratamiento keratina .................. Consultar
Cada item: flex justify-between border-b border-[#2A2A2A] py-3
Precio: text-gold font-medium
CTA al final: "¿Tienes dudas sobre el precio? Llámanos: 633 912 050"

SECCIÓN 5 — Por qué elegirnos (#nosotros)
Diseño: Fondo negro puro. Texto centrado. 4 puntos en grid 2x2.
Título: "¿Por qué Peluquería Jenver?"

4 bloques:
  [Número grande en dorado] "10+"     → "Años de experiencia"
  [Número grande en dorado] "Unisex"  → "Para toda la familia"
  [Número grande en dorado] "#1"      → "Especialistas afro en Montcada"
  [Número grande en dorado] "5★"      → "Valoración media en Google"
Debajo: párrafo breve con keywords:

"Somos la peluquería de referencia en Montcada i Reixac para cabello afro, rizos y técnicas de color como el balayage. Atendemos a clientes de Montcada, Ripollet, Cerdanyola y toda la zona norte de Barcelona."


SECCIÓN 6 — Reseñas (#resenas)
Diseño: Fondo bg-[#111111]. Carrusel o 3 tarjetas estáticas en desktop.
3 reseñas ficticias de ejemplo (placeholders — sustituir por reales de Google):
Reseña 1:
  ★★★★★  "El mejor balayage que me han hecho nunca. Vine desde Barcelona norte porque me lo recomendaron y repetiré sin duda. Ambiente muy acogedor."
  — María G.

Reseña 2:
  ★★★★★  "Especialistas en cabello afro de verdad. Llevan años cuidando mi pelo y siempre salen perfectos los peinados. Los recomiendo a todo el mundo."
  — Aminata K.

Reseña 3:
  ★★★★★  "Fui a por un corte y me quedé con el color también. El trato es increíble y el resultado espectacular. Sin duda la mejor peluquería de Montcada i Reixac."
  — Laura M.
Cada tarjeta: bg-[#1A1A1A] border border-[#2A2A2A] p-6
Estrellas: SVG doradas o emoji ★ en text-gold

SECCIÓN 7 — CTA Reserva (#reserva)
Diseño: Sección de alto impacto. Fondo: imagen oscura con overlay negro intenso. Texto centrado en blanco y dorado.
Texto pequeño superior: "¿Lista para tu cambio de look?"

H2 grande (serif, blanco):
"Reserva tu cita
 en Peluquería Jenver"

Texto:
"Atendemos en Carrer Lleida, 21 · Montcada i Reixac · Barcelona
 Abiertos de martes a sábado, de 9:00 a 19:00"

[Botón: "Llamar ahora: 633 912 050"]  (dorado, grande)
[Botón: "WhatsApp"]  (outline blanco)

href teléfono: "tel:+34633912050"
href WhatsApp: "https://wa.me/34633912050?text=Hola!%20Quería%20pedir%20cita%20en%20Peluquería%20Jenver"

SECCIÓN 8 — Mapa y Contacto (#contacto)
Diseño: 2 columnas. Izquierda: datos de contacto. Derecha: iframe Google Maps.
Izquierda:
  H2: "Cómo llegar"
  📍 Carrer Lleida, 21
     08110 Montcada i Reixac
     Barcelona, España
  📞 633 912 050
  🕐 Martes a Sábado: 9:00 – 19:00
  Link: "Ver en Google Maps →"

Derecha:
  <iframe
    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2990...  (busca embed de Carrer Lleida 21 Montcada i Reixac)"
    width="100%"
    height="350"
    style="border:0; filter: grayscale(100%) invert(10%);"
    allowfullscreen
    loading="lazy">
  </iframe>
Nota: El iframe del mapa tiene filter: grayscale(100%) invert(10%) para que armonice con el diseño oscuro.

INSTRUCCIONES TÉCNICAS PARA CLAUDE CODE
Tailwind Config:

Usa @layer components para clases reutilizables (.btn-gold, .btn-outline, .section-title)
No usar @apply en exceso — preferir clases utilitarias directamente en el HTML

JavaScript (vanilla, mínimo):

Navbar: añadir clase scrolled al hacer scroll > 50px (sombra dorada sutil)
Menú móvil: toggle del menú hamburguesa
NO usar Alpine.js ni ningún framework JS a menos que ya esté en el proyecto

SEO On-Page — reglas importantes:

Un único <h1> por página, siempre en el hero, siempre con la keyword principal
<h2> para cada sección ("Servicios", "Galería", "Contacto", etc.)
<h3> para items dentro de secciones (tarjetas de servicio, etc.)
Todos los <img> con alt descriptivo y con keyword cuando sea natural
Links internos con title descriptivo
El footer debe tener la dirección completa en texto plano (no solo en mapa) para que Google la indexe

Accesibilidad:

aria-label en el botón del menú hamburguesa
aria-current="page" en el link activo del nav
Contraste suficiente: texto blanco/dorado sobre negro ✓

Responsive:

Mobile-first siempre
Breakpoints: sm: (640px), md: (768px), lg: (1024px), xl: (1280px)
El hero en móvil: texto más pequeño, botones en columna
La galería en móvil: 2 columnas
El footer en móvil: 1 columna, apilado


RESUMEN DE ARCHIVOS A ENTREGAR
1. resources/views/layouts/app.blade.php
2. resources/views/partials/header.blade.php
3. resources/views/partials/footer.blade.php
4. resources/views/partials/schema-local.blade.php
5. resources/views/pages/home.blade.php
6. tailwind.config.js  (con la extensión de colores y fuentes)
7. resources/css/app.css  (imports Tailwind + capas @layer)
Crea los 7 archivos completos, sin omitir ninguna sección. Cada archivo debe ser funcional y estar listo para integrarse en un proyecto Laravel 13 con Vite.
