@extends('layouts.app')

@section('robots', 'noindex, follow')
@section('title', 'Avisos Legales | Peluquería Jenver')
@section('description', 'Avisos legales y condiciones de uso de Peluquería Jenver.')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-4xl font-serif font-bold mb-8 text-gold">Avisos Legales</h1>

    <div class="prose prose-invert max-w-none space-y-6">
        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">1. Información del Titular</h2>
            <p class="text-gray-300">
                <strong>Razón Social:</strong> Peluquería Jenver<br>
                <strong>Domicilio:</strong> Carrer Lleida, 21 - 08110 Montcada i Reixac (Barcelona)<br>
                <strong>Teléfono:</strong> +34 633 912 050<br>
                <strong>Email:</strong> peluqueriajenver@email.com
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">2. Objeto y Ámbito de Aplicación</h2>
            <p class="text-gray-300">
                Este sitio web tiene por objeto proporcionar información sobre los servicios de peluquería ofrecidos por Peluquería Jenver y facilitar la reserva de citas. El acceso y uso del sitio implica la aceptación de estos avisos legales.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">3. Limitación de Responsabilidad</h2>
            <p class="text-gray-300">
                Peluquería Jenver no se hace responsable de:
            </p>
            <ul class="list-disc list-inside text-gray-300 space-y-2 ml-4">
                <li>Errores u omisiones en el contenido del sitio</li>
                <li>Daños derivados del mal uso del sitio web</li>
                <li>Interrupciones o fallos en la disponibilidad del servicio</li>
                <li>Pérdida de datos o acceso no autorizado</li>
                <li>Contenido externo vinculado desde nuestro sitio</li>
            </ul>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">4. Propiedad Intelectual</h2>
            <p class="text-gray-300">
                Todos los contenidos de este sitio web (textos, imágenes, logos, diseño, etc.) son propiedad de Peluquería Jenver o están debidamente licenciados. Queda prohibida su reproducción, distribución o transmisión sin consentimiento previo.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">5. Condiciones de Uso</h2>
            <p class="text-gray-300">
                Al utilizar este sitio web te comprometes a:
            </p>
            <ul class="list-disc list-inside text-gray-300 space-y-2 ml-4">
                <li>No interferir con el funcionamiento del sitio</li>
                <li>No intentar acceder a áreas restringidas sin autorización</li>
                <li>No transmitir virus, malware u otro contenido malicioso</li>
                <li>No enviar spam o mensajes no solicitados</li>
                <li>Respetar los derechos de propiedad intelectual</li>
            </ul>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">6. Enlaces Externos</h2>
            <p class="text-gray-300">
                Este sitio web puede contener enlaces a sitios de terceros. Peluquería Jenver no es responsable del contenido, exactitud o prácticas de privacidad de esos sitios externos.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">7. Legislación Aplicable</h2>
            <p class="text-gray-300">
                Estos avisos legales se rigen por la legislación española, en particular por la Ley de Servicios de la Sociedad de la Información y de Comercio Electrónico (LSSI-CE) y el Reglamento General de Protección de Datos (RGPD).
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">8. Modificaciones</h2>
            <p class="text-gray-300">
                Peluquería Jenver se reserva el derecho a modificar estos avisos legales en cualquier momento. Se recomienda revisar periódicamente esta página para estar informado de cualquier cambio.
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
