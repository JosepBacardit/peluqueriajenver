@extends('layouts.app')

@section('robots', 'noindex, follow')
@section('title', 'Política de Privacidad | Peluquería Jenver')
@section('description', 'Política de privacidad de Peluquería Jenver. Conoce cómo tratamos y protegemos tus datos personales.')

{{--
    The data controller's legal name, NIF, contact email and retention
    period were confirmed by the client on 2026-10-06. The one bracketed
    "[Pendiente de confirmar: ...]" value left is the email provider, still
    undecided (it depends on the SMTP setup) — deliberately left visible
    instead of invented, and blocks publishing the booking system (see
    AGENTS.md, "Before deploying the booking system").
--}}

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-4xl font-serif font-bold mb-8 text-gold">Política de Privacidad</h1>

    <div class="prose prose-invert max-w-none space-y-6">
        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">1. Responsable del tratamiento</h2>
            <p class="text-gray-300">
                <strong>Peluquería Jenver</strong><br>
                Titular: Isabel Lechuga Valverde<br>
                NIF: 53650299Q<br>
                C/ Lleida, 21<br>
                08110 Montcada i Reixac (Barcelona)<br>
                Teléfono: +34 633 912 050<br>
                Email: peluqueriajenver@gmail.com
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">2. Datos que tratamos</h2>
            <ul class="list-disc list-inside text-gray-300 space-y-2 ml-4">
                <li><strong>Reservas online:</strong> nombre, teléfono, email, el servicio, el día y la hora elegidos y las observaciones que quieras añadir.</li>
                <li><strong>Citas por teléfono o WhatsApp:</strong> los mismos datos, que el salón apunta en su agenda.</li>
                <li><strong>Navegación:</strong> datos de cookies y de análisis, según la <a href="{{ route('cookies') }}" class="text-gold underline">política de cookies</a>.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">3. Finalidad</h2>
            <p class="text-gray-300">
                Gestionar tu cita: reservarla, enviarte su confirmación con el enlace para consultarla o cancelarla, avisarte si el salón tiene que cancelarla y atenderte el día de la cita. No usamos estos datos para enviarte publicidad.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">4. Base legal</h2>
            <p class="text-gray-300">
                La aplicación, a petición tuya, de medidas precontractuales: tratamos tus datos porque nos pides una cita (artículo 6.1.b del Reglamento General de Protección de Datos). Sin ellos no podemos reservarla ni avisarte sobre ella. Las cookies que no son técnicas se basan en tu consentimiento.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">5. Destinatarios y encargados del tratamiento</h2>
            <p class="text-gray-300">
                No cedemos tus datos a terceros, salvo obligación legal. Para prestar el servicio, estos proveedores los tratan por cuenta del salón:
            </p>
            <ul class="list-disc list-inside text-gray-300 space-y-2 ml-4">
                <li><strong>Alojamiento de la web y de la base de datos:</strong> OVH (servidor privado virtual).</li>
                <li><strong>Envío de los correos de las citas:</strong> [Pendiente de confirmar: proveedor de correo electrónico].</li>
            </ul>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">6. Plazo de conservación</h2>
            <p class="text-gray-300">
                Conservamos los datos de cada cita durante 2 años desde la fecha de la cita y, después, durante los plazos que exija la ley. Pasado ese tiempo se eliminan.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">7. Derechos</h2>
            <p class="text-gray-300">
                Puedes ejercer en cualquier momento tus derechos de:
            </p>
            <ul class="list-disc list-inside text-gray-300 space-y-2 ml-4">
                <li><strong>Acceso</strong>: saber qué datos tuyos tratamos.</li>
                <li><strong>Rectificación</strong>: corregir datos inexactos.</li>
                <li><strong>Supresión</strong>: pedir que eliminemos tus datos.</li>
                <li><strong>Limitación</strong>: pedir que limitemos su tratamiento.</li>
                <li><strong>Portabilidad</strong>: recibirlos en un formato estructurado.</li>
                <li><strong>Oposición</strong>: oponerte a su tratamiento.</li>
            </ul>
            <p class="text-gray-300">
                Para ejercerlos, escríbenos a peluqueriajenver@gmail.com o llámanos al +34 633 912 050. Si crees que no hemos atendido bien tu solicitud, puedes presentar una reclamación ante la Agencia Española de Protección de Datos (www.aepd.es).
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-serif font-semibold mt-8 mb-4 text-gold">8. Seguridad de los datos</h2>
            <p class="text-gray-300">
                Aplicamos medidas técnicas y organizativas para proteger tus datos personales contra el acceso no autorizado, la alteración o la destrucción. Solo el personal del salón, con una cuenta personal, puede ver la agenda de citas.
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
