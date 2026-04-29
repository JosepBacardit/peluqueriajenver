@extends('layouts.app')

@section('robots', 'noindex, follow')
@section('title', 'Política de Cookies | Peluquería Jenver')
@section('description', 'Política de cookies de Peluquería Jenver. Información sobre las cookies que utilizamos en nuestro sitio.')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-4xl font-serif font-bold mb-8 text-gold">Política de Cookies</h1>

    <div class="prose prose-invert max-w-none space-y-6">
        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">¿Qué son las Cookies?</h2>
            <p class="text-gray-300">
                Las cookies son pequeños ficheros de texto que se almacenan en el dispositivo del usuario cuando visita un sitio web. Se utilizan para mejorar la experiencia del usuario, recordar preferencias y recopilar información analítica.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">Tipos de Cookies que Utilizamos</h2>

            <h3 class="text-xl font-semibold mt-6 mb-3 text-gold/80">Cookies Técnicas o Necesarias</h3>
            <p class="text-gray-300">
                Esenciales para el funcionamiento del sitio web. Permiten la navegación y funcionalidades básicas como acceso a áreas protegidas.
            </p>

            <h3 class="text-xl font-semibold mt-6 mb-3 text-gold/80">Cookies de Análisis</h3>
            <p class="text-gray-300">
                Utilizamos Google Analytics para recopilar información anónima sobre cómo los usuarios interactúan con nuestro sitio (páginas visitadas, tiempo de permanencia, etc.). Esta información nos ayuda a mejorar la experiencia del usuario.
            </p>

            <h3 class="text-xl font-semibold mt-6 mb-3 text-gold/80">Cookies de Publicidad</h3>
            <p class="text-gray-300">
                Utilizamos Google Tag Manager para gestionar etiquetas y rastrear conversiones. Estas cookies nos permiten entender qué estrategias son más efectivas.
            </p>

            <h3 class="text-xl font-semibold mt-6 mb-3 text-gold/80">Cookies de Preferencia</h3>
            <p class="text-gray-300">
                Almacenan tus preferencias de idioma, tema de color, y otras configuraciones personales.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">Gestión de Cookies</h2>
            <p class="text-gray-300">
                La mayoría de navegadores web te permiten:
            </p>
            <ul class="list-disc list-inside text-gray-300 space-y-2 ml-4">
                <li>Ver qué cookies están almacenadas</li>
                <li>Eliminar cookies específicas o todas</li>
                <li>Bloquear cookies de terceros o todas las cookies</li>
                <li>Recibir avisos antes de que se almacene una cookie</li>
            </ul>
            <p class="text-gray-300 mt-4">
                Puedes cambiar tu configuración de cookies en la configuración de privacidad de tu navegador. Sin embargo, bloquear ciertas cookies puede afectar la funcionalidad del sitio.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">Servicios Terceros</h2>

            <h3 class="text-xl font-semibold mt-6 mb-3 text-gold/80">Google Analytics</h3>
            <p class="text-gray-300">
                Utilizamos Google Analytics para analizar el tráfico del sitio. Google puede compartir información agregada con terceros. Para más información, consulta la <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="text-gold hover:text-gold-light">política de privacidad de Google</a>.
            </p>

            <h3 class="text-xl font-semibold mt-6 mb-3 text-gold/80">Google Tag Manager</h3>
            <p class="text-gray-300">
                GTM es una herramienta que gestiona etiquetas de seguimiento en nuestro sitio. Puede recopilar datos sobre tu navegación para mejorar nuestros servicios.
            </p>

            <h3 class="text-xl font-semibold mt-6 mb-3 text-gold/80">Google Maps</h3>
            <p class="text-gray-300">
                Utilizamos Google Maps para mostrar nuestra ubicación. Google puede usar cookies para analizar el uso de este servicio.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">Tu Consentimiento</h2>
            <p class="text-gray-300">
                Al utilizar este sitio web, aceptas el uso de cookies conforme a esta política. Si no deseas que se utilicen cookies, puedes deshabilitarlas en tu navegador, aunque esto puede afectar tu experiencia en el sitio.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">Contacto</h2>
            <p class="text-gray-300">
                Si tienes preguntas sobre nuestra política de cookies, ponte en contacto con nosotros:
            </p>
            <p class="text-gray-300">
                <strong>Email:</strong> peluqueriajenver@email.com<br>
                <strong>Teléfono:</strong> +34 633 912 050
            </p>
        </section>

        <section>
            <p class="text-gray-400 text-sm mt-12">
                <em>Última actualización: {{ now()->format('d/m/Y') }}</em>
            </p>
        </section>
    </div>
</div>
@endsection
