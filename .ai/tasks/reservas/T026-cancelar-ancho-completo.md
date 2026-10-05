# T026 — Botón de cancelar a ancho completo en /cita

- **Tipo:** SIMPLE
- **Puntos de referencia:** PRF-098
- **Depende de:** T008 (página de la cita)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `low`
- **Motivo:** una clase Tailwind en un botón.
- **Estado:** done
- **PR / rama:** `feature/mobile-admin-ux`

## Objetivo

El botón «Cancelar mi cita» de `/cita/{token}` es la acción principal de esa página para una clienta que todavía puede cancelar, pero ocupaba solo el ancho de su texto. Decisión del usuario, 2026-10-05 (recomendación de la fase 1, «bonito tener»): ancho completo, más fácil de tocar.

## Cambios

`resources/views/pages/cita.blade.php`: el botón «Cancelar mi cita» pasa de `btn-outline` a `btn-outline w-full`.

## Evidencia

`tests/Feature/Booking/CustomerAppointmentTest.php`, test nuevo «the cancel button spans the full width on a cancellable appointment»: comprueba que el HTML exacto del botón incluye `w-full`.

## Verificación

`docker compose exec -u www-data app php artisan test --compact --filter="CustomerAppointmentTest"` (12 tests en verde) · suite completa (355 tests en verde) · `docker compose exec -u www-data app vendor/bin/pint --dirty --format agent` (sin cambios).
