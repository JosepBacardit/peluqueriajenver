@extends('layouts.app')

@section('robots', 'noindex, follow')
@section('title', 'Política de Privacidad | Peluquería Jenver')
@section('description', 'Política de privacidad de Peluquería Jenver. Conoce cómo tratamos y protegemos tus datos personales.')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-4xl font-serif font-bold mb-8 text-gold">Política de Privacidad</h1>

    <div class="prose prose-invert max-w-none space-y-6">
        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">1. Responsable del Tratamiento</h2>
            <p class="text-gray-300">
                <strong>Peluquería Jenver</strong><br>
                Carrer Lleida, 21<br>
                08110 Montcada i Reixac (Barcelona)<br>
                Teléfono: +34 633 912 050<br>
                Email: peluqueriajenver@email.com
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">2. Información que Recopilamos</h2>
            <p class="text-gray-300">
                Recopilamos información que nos proporcionas directamente, como:
            </p>
            <ul class="list-disc list-inside text-gray-300 space-y-2 ml-4">
                <li>Nombre y número de teléfono para reservas</li>
                <li>Dirección de correo electrónico</li>
                <li>Información sobre tus preferencias de servicios</li>
                <li>Datos de cookies y análisis de navegación</li>
            </ul>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">3. Base Legal del Tratamiento</h2>
            <p class="text-gray-300">
                El tratamiento de tus datos personales se realiza en base a:
            </p>
            <ul class="list-disc list-inside text-gray-300 space-y-2 ml-4">
                <li>Tu consentimiento explícito (reservas y contacto)</li>
                <li>Ejecución de un contrato (citas y servicios)</li>
                <li>Cumplimiento de obligaciones legales</li>
                <li>Intereses legítimos de la empresa</li>
            </ul>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">4. Derechos del Usuario</h2>
            <p class="text-gray-300">
                Según la RGPD, tienes derecho a:
            </p>
            <ul class="list-disc list-inside text-gray-300 space-y-2 ml-4">
                <li><strong>Acceso</strong>: Solicitar acceso a tus datos personales</li>
                <li><strong>Rectificación</strong>: Corregir datos inexactos</li>
                <li><strong>Supresión</strong>: Solicitar la eliminación de tus datos</li>
                <li><strong>Restricción</strong>: Limitar el procesamiento de tus datos</li>
                <li><strong>Portabilidad</strong>: Obtener tus datos en formato estructurado</li>
                <li><strong>Oposición</strong>: Oponerte al tratamiento de tus datos</li>
            </ul>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">5. Seguridad de los Datos</h2>
            <p class="text-gray-300">
                Implementamos medidas técnicas y organizativas para proteger tus datos personales contra acceso no autorizado, alteración o destrucción.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">6. Contacto</h2>
            <p class="text-gray-300">
                Para ejercer tus derechos o si tienes preguntas sobre esta política, ponte en contacto con nosotros en:
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
