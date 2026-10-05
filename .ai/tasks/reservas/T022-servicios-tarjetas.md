# T022 — Servicios en tarjetas en todos los anchos

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-091
- **Depende de:** T002 (módulo de servicios)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `low`
- **Motivo:** cambio de presentación de una sola vista, sin tocar el controlador ni las reglas de negocio.
- **Estado:** done (pendiente comprobar a 375 px en el navegador, la hace el usuario)
- **PR / rama:** `feature/mobile-admin-ux`

## Objetivo

`admin/services/index.blade.php` era una `<table>` de 7 columnas envuelta en `overflow-x-auto`: en 360-414 px obligaba a hacer *scroll* horizontal para ver precio, reservable, activo y «Editar». Decisión del usuario, 2026-10-05 (recomendación de la fase 1, alternativa 2): tarjetas en todos los anchos de pantalla, no solo en móvil, para no duplicar marcado entre una tabla de escritorio y unas tarjetas de móvil — el catálogo de un salón es corto y no necesita una tabla densa ni en escritorio.

## Cambios

`resources/views/admin/services/index.blade.php`: la `<table>` se sustituye por `<ul class="space-y-3">` con una tarjeta (`<li class="border border-[#2A2A2A] p-4">`) por servicio, con el mismo estilo que ya usan Agenda y Cierres. Cada tarjeta muestra el nombre y el orden en la cabecera, «Editar» como `btn-outline` (zona táctil de 44 px, PRF-089) y precio/reservable/activo en una `<dl>` de 3 columnas. Un servicio no activo se atenúa con `opacity-60`, igual que una cita cancelada en la agenda. El controlador (`ServiceController`) no cambia: sigue ordenando por `sort_order` y nombre.

## Evidencia

`tests/Feature/Admin/ServiceManagementTest.php`, test nuevo «the list is rendered as cards, never a table»: comprueba que la respuesta no contiene `<table` ni `overflow-x-auto`, y que el enlace «Editar» apunta a la ruta correcta. El test existente «the list is ordered by sort order then name and shows every column» sigue en verde sin cambios: comprobaba texto (`assertSeeInOrder`, `assertSee`), no la estructura de la tabla.

## Verificación

`docker compose exec -u www-data app php artisan test --compact --filter="ServiceManagementTest"` (20 tests en verde) · suite completa (348 tests en verde) · `docker compose exec -u www-data app vendor/bin/pint --dirty --format agent` (sin cambios).

Pendiente a mano (la hace el usuario): a 375 px, confirmar que la lista de servicios no produce *scroll* horizontal y que «Editar» es fácil de tocar.
