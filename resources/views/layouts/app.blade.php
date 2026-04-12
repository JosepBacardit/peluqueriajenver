<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Meta tags dinámicos -->
    <title>@yield('title', 'Peluquería Jenver | Montcada i Reixac')</title>
    <meta name="description" content="@yield('description', 'Peluquería unisex en Montcada i Reixac. Especialistas en balayage, cabello afro y rizos. Corte, color y extensiones. Reserva tu cita: 633 912 050.')">
    <meta name="keywords" content="@yield('keywords', 'peluquería Montcada i Reixac, balayage, cabello afro, rizos, peluquería unisex')">

    <!-- Open Graph -->
    <meta property="og:title" content="@yield('og_title', 'Peluquería Jenver | Montcada i Reixac')">
    <meta property="og:description" content="@yield('og_description', 'Especialistas en balayage, cabello afro y rizos en Montcada i Reixac, Barcelona.')">
    <meta property="og:image" content="@yield('og_image', asset('images/logo-jenver.png'))">
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

    <main class="pt-24">
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')
</body>
</html>
