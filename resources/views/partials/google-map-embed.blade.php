{{--
    Reusable Google Maps embed, gated behind an explicit click.

    Loading the iframe makes a request to Google that can set its own
    cookies before the visitor has any say, and it is unrelated to the
    analytics cookie-consent gating in cookie-banner.blade.php and
    layouts/app.blade.php (see .ai/reviews/cookie-consent.md). The iframe is
    created from JS only, from the data-embed-src attribute below; the
    server never renders a src attribute pointing at Google.

    The click listener that creates the iframe lives once in
    layouts/app.blade.php, delegated on document, so including this partial
    more than once on the same page never duplicates any JS.

    Required params: $id (unique per inclusion on the page), $embedSrc,
    $height (px), $address, $directionsUrl.
--}}
<div
    id="google-map-{{ $id }}"
    class="relative w-full bg-[#1A1A1A] border border-gold/30 rounded overflow-hidden flex items-center justify-center text-center px-6"
    style="height: {{ $height }}px;"
    data-google-map
    data-embed-src="{{ $embedSrc }}"
>
    <div data-google-map-placeholder class="py-6">
        <p class="text-gold font-serif font-semibold mb-1">Peluquería Jenver</p>
        <p class="text-gray-300 text-sm mb-4">{{ $address }}</p>
        <button type="button" class="btn-gold text-sm font-semibold px-5 py-2" data-google-map-trigger>
            Ver mapa
        </button>
        <p class="text-gray-400 text-xs mt-3 max-w-xs mx-auto">
            Al cargar el mapa, Google puede instalar cookies.
            <a href="{{ route('cookies') }}" class="text-gold hover:text-gold-light underline">Más información</a>
        </p>
        <a href="{{ $directionsUrl }}" target="_blank" rel="noopener" class="inline-block mt-3 text-gold hover:text-gold-light underline text-sm">
            Abrir en Google Maps
        </a>
    </div>
</div>
