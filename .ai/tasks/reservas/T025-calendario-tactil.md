# T025 — Calendario y horas públicas: zona táctil

- **Tipo:** SIMPLE
- **Puntos de referencia:** PRF-097
- **Depende de:** T007 (reserva pública)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `low`
- **Motivo:** ajuste de clases Tailwind en dos partials públicos, sin lógica nueva.
- **Estado:** done (pendiente confirmar la medición a 375 px, el agente principal comprueba lo público)
- **PR / rama:** `feature/mobile-admin-ux`

## Objetivo

Las celdas del calendario (`pages/partials/reservas-calendar.blade.php`) y los botones de hora (`pages/partials/reservas-form.blade.php`) usaban `py-2`, que en una rejilla de 7 u 8 columnas a 375 px da una altura real por debajo de los 44 px recomendados. Es la parte de la reserva pública que más toca la clienta en el móvil.

## Cambios

- `pages/partials/reservas-calendar.blade.php`: cada celda de día (disponible o no) pasa de `py-2` a `min-h-11 flex items-center justify-center`, para una altura mínima de 44 px con el número centrado, con independencia del ancho de columna.
- `pages/partials/reservas-form.blade.php`: el `<span>` visible de cada hora (el `<input type="radio">` real está oculto con `sr-only`, como ya hacía) pasa de `block text-center py-2` a `min-h-11 flex items-center justify-center`, mismo criterio.

## Evidencia

`tests/Feature/Booking/PublicBookingTest.php`, test nuevo «the calendar days and the time slots meet the 44px minimum touch target»: comprueba que la clase `min-h-11 flex items-center justify-center border` aparece más de 30 veces en la página (una por cada día del mes, disponible o no, más una por cada hora ofrecida), como guarda de regresión sobre el marcado.

## Verificación

`docker compose exec -u www-data app php artisan test --compact --filter="PublicBookingTest"` (35 tests en verde) · suite completa (354 tests en verde) · `docker compose exec -u www-data app vendor/bin/pint --dirty --format agent` (sin cambios).

Pendiente: medir con el inspector, a 375 px, que una celda del calendario y un botón de hora miden al menos 44 px de alto (el agente principal lo comprueba con el navegador en las páginas públicas, que no necesitan sesión).
