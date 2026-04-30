<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-NP6KXF9K');</script>
    <!-- End Google Tag Manager -->

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-EX4HPXH0WV"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-EX4HPXH0WV');
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Meta tags dinámicos -->
    <title>@yield('title', 'Peluquería Jenver | Montcada i Reixac')</title>
    <meta name="description" content="@yield('meta_description', 'Peluquería unisex en Montcada i Reixac. Especialistas en balayage, cabello afro y rizos. Corte, color y extensiones. Reserva tu cita: 633 912 050.')">
    <meta name="keywords" content="@yield('keywords', 'peluquería Montcada i Reixac, balayage, cabello afro, rizos, peluquería unisex')">
    <meta name="robots" content="@yield('robots', 'index, follow')">

    <!-- Open Graph -->
    <meta property="og:title" content="@yield('og_title', 'Peluquería Jenver | Montcada i Reixac')">
    <meta property="og:description" content="@yield('og_description', 'Especialistas en balayage, cabello afro y rizos en Montcada i Reixac, Barcelona.')">
    <meta property="og:image" content="@yield('og_image', asset('images/logo-jenver.png'))">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_ES">

    <!-- Canonical -->
    <link rel="canonical" href="@yield('canonical', url()->current())">

    <!-- Sitemap -->
    <link rel="sitemap" type="application/xml" href="{{ route('sitemap') }}">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Preconnect para optimización de recursos externos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Tailwind + App CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Schema JSON-LD SEO Local -->
    @include('partials.schema-local')

    @stack('structured_data')
    @stack('head')
</head>
<body class="bg-[#0A0A0A] text-white font-sans antialiased">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-NP6KXF9K"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    @include('partials.header')

    <main class="pt-24">
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')
</body>
</html>
