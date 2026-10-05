# T024 — Horario semanal sin recorte en 360 px

- **Tipo:** SIMPLE
- **Puntos de referencia:** PRF-096
- **Depende de:** T003 (horario y ajustes)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `low`
- **Motivo:** ajuste de clases Tailwind en una sola vista, sin lógica ni validación nuevas.
- **Estado:** done (pendiente comprobar a 360 px en el navegador, la hace el usuario)
- **PR / rama:** `feature/mobile-admin-ux`

## Objetivo

Cada fila de tramo (`admin/opening-hours/edit.blade.php`) es una etiqueta («Tramo 1»/«Tramo 2»), dos `<input type="time">` nativos y un guion en una fila `flex` sin `flex-wrap`. A 360 px de ancho el hueco disponible dentro del `<fieldset>` es muy justo para los dos selectores de hora nativos más la etiqueta: con la fuente y el motor de render de algunos móviles podía recortarse o forzar *scroll* horizontal dentro de la fila.

## Cambios

`resources/views/admin/opening-hours/edit.blade.php`: la fila de cada tramo pasa de `flex items-center gap-2 text-sm` a `flex flex-wrap items-center gap-2 text-sm`, con `shrink-0` en la etiqueta y en el guion y `min-w-0` en los dos `<input type="time">`. Si no cabe todo en una línea a 360 px, la hora de fin pasa a la siguiente línea en vez de recortarse o desbordar; la etiqueta y los `aria-label` no cambian.

## Evidencia

`tests/Feature/Admin/OpeningHoursManagementTest.php`, test nuevo «every opening-hours range row can wrap instead of overflowing on a narrow screen»: comprueba que las 14 filas (7 días × 2 tramos) llevan la clase `flex flex-wrap items-center gap-2 text-sm` y que siguen presentes los 28 `<input type="time">`. Es un test de regresión sobre las clases, no sobre píxeles: la comprobación real a 360 px queda para el navegador.

## Verificación

`docker compose exec -u www-data app php artisan test --compact --filter="OpeningHoursManagementTest"` (10 tests en verde) · suite completa (353 tests en verde) · `docker compose exec -u www-data app vendor/bin/pint --dirty --format agent` (sin cambios).

Pendiente a mano (la hace el usuario): a 360 px, abrir Horario y comprobar que ninguna fila de tramo produce *scroll* horizontal ni recorta los campos de hora.
