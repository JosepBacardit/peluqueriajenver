# T016 — Marca del salón en el tema de los correos

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-072, PRF-073, PRF-074, PRF-075
- **Depende de:** T010 (Mailables y vistas Markdown ya existen)
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `medium`
- **Motivo:** no cambia el dominio de reservas, pero toca un tema de correo compartido por los 4 Mailables y tiene que verse bien en varios clientes de correo.
- **Estado:** done
- **PR / rama:** `feature/booking-admin-tweaks`, apilada sobre `feature/booking-review-fixes`

## Contexto

Los 4 Mailables (`app/Mail/AppointmentConfirmedMail.php`, `AppointmentCancelledMail.php`, `NewAppointmentMail.php`, `CustomerCancelledAppointmentMail.php`) usan `Content(markdown: 'mail.xxx', ...)` con vistas `<x-mail::message>` en `resources/views/mail/*.blade.php`. No hay `resources/views/vendor/mail/` publicado, así que usan el tema `default` del paquete `laravel/framework` (logo de Laravel enlazado a laravel.com, pie «© {año} Laravel. All rights reserved.»). `config/mail.php` no tiene clave `'markdown'`. Logos disponibles en `public/images/`: `logo-jenver-optimized-v2.png` (y su `.webp`, que no se usa aquí). El usuario aprueba el logo en PNG como excepción a la regla de WebP, solo para el correo.

## Plan

1. `php artisan vendor:publish --tag=laravel-mail` (o copiar a mano los archivos del tema) para obtener `resources/views/vendor/mail/html/themes/default.css`, `header.blade.php`, `footer.blade.php`, `message.blade.php`, etc.
2. Crear un tema propio `jenver` (no tocar `default`, para que una futura actualización del framework no lo pise): `resources/views/vendor/mail/html/themes/jenver.css` con fondo negro/gris oscuro, acentos dorados y tipografía legible en los clientes de correo habituales (sin CSS moderno: Outlook de escritorio usa el motor de Word).
3. `header.blade.php` del tema: logo `public/images/logo-jenver-optimized-v2.png` referenciado con `asset()` (URL absoluta), `alt="Peluquería Jenver"`, enlazado a `https://www.peluqueriajenver.com`.
4. `footer.blade.php` del tema: dirección (C/ Lleida, 21 · Montcada i Reixac), teléfono (633 912 050) y «© {{ now()->year }} Peluquería Jenver» en vez del pie de Laravel.
5. `config/mail.php`: añadir `'markdown' => ['theme' => 'jenver', 'paths' => [resource_path('views/vendor/mail')]]`.
6. Las 4 vistas `resources/views/mail/*.blade.php` no cambian de contenido.

## Plan de pruebas

Ampliar `tests/Feature/Booking/MailContentEscapingTest.php` (o un test nuevo `MailBrandingTest.php`) que renderice los 4 Mailables y compruebe: ausencia de `laravel.com` y de «Laravel» en el pie, presencia de «Peluquería Jenver» en el pie, y que la URL del logo es absoluta (empieza por `http`).

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent`. Además, renderizar el HTML de los 4 correos a un archivo (sin comprometerlo) para revisión visual manual en Gmail y Outlook.

## Riesgos

Un tema mal maquetado puede romperse en Outlook de escritorio. Se mitiga manteniendo la estructura de tablas del tema `default` y solo cambiando colores, logo y pie.
