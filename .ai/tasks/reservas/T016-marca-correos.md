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

Ejecutado con una simplificación respecto al plan original de fase 1 (principio de simplicidad quirúrgica): en vez de un tema con nombre propio (`jenver`) en paralelo al `default`, se publica y se edita directamente `resources/views/vendor/mail/`, que es ya exclusivo de este proyecto en cuanto se publica (una actualización de `laravel/framework` no lo toca) — así no hace falta ningún cambio en `config/mail.php`.

1. `php artisan vendor:publish --tag=laravel-mail`, que crea `resources/views/vendor/mail/html/` y `text/` con los archivos del tema por defecto.
2. `resources/views/vendor/mail/html/themes/default.css`: franja de cabecera en negro (`#000000`), títulos y enlaces en dorado oscuro (`#a07830`, mismo valor que `--color-gold-dark` de `resources/css/app.css`, por contraste de lectura sobre blanco), botón principal en dorado (`#c9a84c`, `--color-gold`) con texto oscuro (igual que `.btn-gold` del sitio), borde del panel en dorado.
3. `resources/views/vendor/mail/html/message.blade.php` y `text/message.blade.php`: cabecera con el logo `public/images/logo-jenver-optimized-v2.png` vía `asset()` (URL absoluta), `alt="Peluquería Jenver"`, dentro del enlace a `config('app.url')`; pie con «Peluquería Jenver · C/ Lleida, 21 · 08110 Montcada i Reixac · 633 912 050» y «© {año} Peluquería Jenver. Todos los derechos reservados.» en vez del pie de Laravel. No hace falta tocar `header.blade.php`: su condición `trim($slot) === 'Laravel'` ya deja de activarse en cuanto el slot es la etiqueta `<img>` del logo.
4. Las 4 vistas `resources/views/mail/*.blade.php` no cambian de contenido.
5. No se toca `config/mail.php`: Laravel usa `resources/views/vendor/mail` automáticamente en cuanto existe, sin declarar ningún tema nuevo.

## Plan de pruebas

`tests/Feature/Booking/MailBrandingTest.php` (nuevo), con los 4 Mailables: ausencia de `laravel.com` y de «Laravel»; presencia de «Peluquería Jenver», de la dirección y del teléfono; presencia del color dorado inlineado (`#c9a84c`); y que el `<img class="logo">` tiene `src` absoluta (`config('app.url')` como prefijo) y `alt="Peluquería Jenver"`.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (258 tests, verde) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` (sin cambios). Los 4 correos se han renderizado a `storage/app/mail-preview/*.html` (no comprometido, `storage/app/.gitignore` ya lo excluye) para la revisión visual manual en Gmail y Outlook que debe hacer el usuario.

## Riesgos

Un tema mal maquetado puede romperse en Outlook de escritorio. Se mitiga manteniendo la estructura de tablas del tema `default` y solo cambiando colores, logo y pie.
