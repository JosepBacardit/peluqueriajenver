<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Google Tag Manager (required early for tracking) -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-NP6KXF9K');</script>
    <!-- End Google Tag Manager -->

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
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="es_ES">
    <meta property="og:site_name" content="Peluquería Jenver">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', 'Peluquería Jenver | Montcada i Reixac')">
    <meta name="twitter:description" content="@yield('og_description', 'Especialistas en balayage, cabello afro y rizos.')">
    <meta name="twitter:image" content="@yield('og_image', asset('images/logo-jenver.png'))">

    <!-- Canonical -->
    <link rel="canonical" href="@yield('canonical', url()->current())">

    <!-- Sitemap -->
    <link rel="sitemap" type="application/xml" href="{{ route('sitemap') }}">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

    <!-- DNS prefetch y preconnect para Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Preload crítico de fuentes woff2 para evitar FOUT -->
    <link rel="preload" as="font" type="font/woff2" href="https://fonts.gstatic.com/s/playfairdisplay/v30/nuFiD-vYS-_2YttRW7dM7IitM_b85eLs6Gs.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3i6t4kDjJwO5Do-5d-PXqqKOnQigVc.woff2" crossorigin>

    <!-- Preconnect and preload fonts for early discovery -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Preload font CSS to hint browser to prioritize download -->
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap">

    <!-- Load Google Fonts asynchronously to avoid render blocking -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"></noscript>

    <!-- System font stack with metrics that match Google Fonts -->
    <style>
      :root {
        --font-serif: 'Playfair Display', Georgia, serif;
        --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', sans-serif;
      }

      body {
        /* Fallback: system fonts with similar metrics to prevent FOUT */
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', sans-serif;
        font-size: 16px;
        line-height: 1.5;
        /* Normalize character height to reduce shift when fonts load */
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
      }

      h1, h2, h3, h4, h5, h6, .font-serif {
        font-family: Georgia, serif;
      }
    </style>

    <!-- Tailwind + App CSS - Load asynchronously to avoid render blocking -->
    <link rel="preload" as="style" href="{{ Vite::asset('resources/css/app.css') }}">
    <link rel="stylesheet" href="{{ Vite::asset('resources/css/app.css') }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ Vite::asset('resources/css/app.css') }}"></noscript>

    <!-- Critical JavaScript - Synchronous for immediate interactivity -->
    <script type="module" src="{{ Vite::asset('resources/js/critical.js') }}"></script>

    <!-- Schema JSON-LD SEO Local -->
    @include('partials.schema-local')

    <!-- Schema JSON-LD Breadcrumb -->
    @include('partials.schema-breadcrumb')

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

    @include('partials.cookie-banner')

    @stack('scripts')

    <!-- Defer analytics until after page load to avoid reflows -->
    <script>
      // Load Google Analytics after page renders
      window.addEventListener('load', function() {
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=G-EX4HPXH0WV';
        document.head.appendChild(script);

        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-EX4HPXH0WV');
      });

      // Load Ahrefs Analytics after page renders
      window.addEventListener('load', function() {
        const script = document.createElement('script');
        script.src = 'https://analytics.ahrefs.com/analytics.js';
        script.setAttribute('data-key', '13MiFXBj6SD9DxTnh4TmCQ');
        script.async = true;
        document.body.appendChild(script);
      });
    </script>
</body>
</html>
