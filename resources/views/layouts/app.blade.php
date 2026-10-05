<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Google Tag Manager (required early for tracking; gated on cookie consent, see .ai/reviews/reservas.md M4) -->
    <script>
      (function () {
        let loaded = false;
        function loadGoogleTagManager() {
          if (loaded) {
            return;
          }
          loaded = true;
          (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
          new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
          j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
          'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
          })(window,document,'script','dataLayer','GTM-NP6KXF9K');
        }

        window.__analyticsConsentLoaders = window.__analyticsConsentLoaders || [];
        window.__analyticsConsentLoaders.push(loadGoogleTagManager);

        // A blocked localStorage must never load a tracker: treat the throw
        // as "no confirmed consent".
        let consentAccepted = false;
        try {
          consentAccepted = localStorage.getItem('cookieConsent') === 'accepted';
        } catch (e) {
          consentAccepted = false;
        }

        if (consentAccepted) {
          loadGoogleTagManager();
        }
      })();
    </script>
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

    {{--
        Fonts are self-hosted (public/fonts/, @font-face rules in
        resources/css/app.css) so no request and no visitor IP ever reaches
        Google's font servers. Only the two weights needed before first
        paint of the hero text (Playfair Display 400, Inter 400) are
        preloaded here; the rest load with the rest of app.css below.
        crossorigin is required even for a same-origin font preload, or
        the browser fetches it twice.
    --}}
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/playfair-display-latin-400-normal.woff2') }}" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter-latin-400-normal.woff2') }}" crossorigin>

    {{--
        Bug found in browser (not by the independent reviewer, see
        .ai/reviews/self-hosted-fonts.md): this plain, unlayered <style>
        set a literal fallback as the real font-family for body/h1-h6
        ("font-family: -apple-system..."/"Georgia, serif"), never
        var(--font-serif)/var(--font-sans). Unlayered CSS always wins over
        Tailwind's utilities, which live inside @layer, so Playfair
        Display and Inter were downloaded and preloaded but never
        actually applied anywhere on the site. Now body/h1-h6 use the
        variables themselves, whose fallback segment is only shown while
        the webfont downloads (font-display: swap).

        The two local-only @font-face rules below (Arial/Georgia,
        read: as the system already has them, no network request) are
        metric-adjusted so the fallback occupies almost the same vertical
        space as the real webfont, to shrink the swap's layout shift.
        Overrides computed from @capsizecss/metrics (the same font-metrics
        database next/font uses): size-adjust = (webfont avg char width /
        unitsPerEm) ÷ (fallback avg char width / unitsPerEm); ascent/
        descent/line-gap-override = the webfont's own value (as a
        fraction of its unitsPerEm) ÷ that size-adjust. ascent-override/
        descent-override/line-gap-override have no effect on Safari
        (Chromium 87+/Firefox 89+ only); harmless there, just no CLS gain.
    --}}
    <style>
      @font-face {
        font-family: 'Playfair Display Fallback';
        src: local('Georgia');
        ascent-override: 106.72%;
        descent-override: 24.76%;
        line-gap-override: 0%;
        size-adjust: 101.39%;
      }

      @font-face {
        font-family: 'Inter Fallback';
        src: local('Arial');
        ascent-override: 90.44%;
        descent-override: 22.52%;
        line-gap-override: 0%;
        size-adjust: 107.12%;
      }

      :root {
        --font-serif: 'Playfair Display', 'Playfair Display Fallback', Georgia, serif;
        --font-sans: 'Inter', 'Inter Fallback', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', sans-serif;
      }

      body {
        font-family: var(--font-sans);
        font-size: 16px;
        line-height: 1.5;
        /* Normalize character height to reduce shift when fonts load */
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
      }

      h1, h2, h3, h4, h5, h6, .font-serif {
        font-family: var(--font-serif);
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
    {{-- The GTM noscript beacon is removed: it fires with no JS at all, so it
         cannot be gated behind cookie consent like the scripts above (see
         .ai/reviews/reservas.md M4). --}}

    @include('partials.header')

    <main class="pt-24">
        @yield('content')
    </main>

    @include('partials.footer')

    @include('partials.cookie-banner')

    @stack('scripts')

    <!-- Defer analytics until after page load to avoid reflows; gated on cookie consent (see .ai/reviews/reservas.md M4) -->
    <script>
      (function () {
        function loadGoogleAnalytics() {
          const script = document.createElement('script');
          script.async = true;
          script.src = 'https://www.googletagmanager.com/gtag/js?id=G-EX4HPXH0WV';
          document.head.appendChild(script);

          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', 'G-EX4HPXH0WV');
        }

        function loadAhrefsAnalytics() {
          const script = document.createElement('script');
          script.src = 'https://analytics.ahrefs.com/analytics.js';
          script.setAttribute('data-key', '13MiFXBj6SD9DxTnh4TmCQ');
          script.async = true;
          document.body.appendChild(script);
        }

        let loaded = false;
        function loadDeferredAnalytics() {
          if (loaded) {
            return;
          }
          loaded = true;
          loadGoogleAnalytics();
          loadAhrefsAnalytics();
        }

        window.__analyticsConsentLoaders = window.__analyticsConsentLoaders || [];
        window.__analyticsConsentLoaders.push(loadDeferredAnalytics);

        // Load Google Analytics and Ahrefs after page renders, same as before.
        window.addEventListener('load', function() {
          let consentAccepted = false;
          try {
            consentAccepted = localStorage.getItem('cookieConsent') === 'accepted';
          } catch (e) {
            consentAccepted = false;
          }

          if (consentAccepted) {
            loadDeferredAnalytics();
          }
        });
      })();
    </script>

    <!--
        Google Maps embeds (partials/google-map-embed.blade.php) only load
        when the visitor clicks "Ver mapa"; this is independent of the
        analytics cookie consent above and never reads or writes it. One
        delegated listener here covers every map on the page, however many
        times the partial is included.
    -->
    <script>
      document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-google-map-trigger]');
        if (!trigger) {
          return;
        }

        const container = trigger.closest('[data-google-map]');
        if (!container || container.querySelector('iframe')) {
          return;
        }

        const iframe = document.createElement('iframe');
        iframe.src = container.getAttribute('data-embed-src');
        iframe.title = 'Mapa de ubicación de Peluquería Jenver';
        iframe.width = '100%';
        iframe.height = '100%';
        iframe.style.position = 'absolute';
        iframe.style.inset = '0';
        iframe.style.border = '0';
        iframe.style.filter = 'grayscale(100%) invert(10%)';
        iframe.referrerPolicy = 'no-referrer-when-downgrade';
        iframe.setAttribute('allowfullscreen', '');
        // The "Ver mapa" button is removed right below: move the focus to
        // the iframe once it loads so it is never left on a detached element.
        iframe.addEventListener('load', function () {
          iframe.focus();
        });
        container.appendChild(iframe);

        const placeholder = container.querySelector('[data-google-map-placeholder]');
        if (placeholder) {
          placeholder.remove();
        }
      });
    </script>
</body>
</html>
