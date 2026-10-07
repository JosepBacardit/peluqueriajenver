# T045 — Servicio preseleccionado en «Nueva cita»

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-124 (preselección)
- **Depende de:** T043
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Estado:** done
- **PR / rama:** `feature/agenda-service-filter`

## Objetivo

Que tocar «Nueva cita» (el botón general o un hueco libre, con o sin «Cabe») abra el formulario con el servicio ya elegido en la agenda preseleccionado — sin repetir la validación de `servicio` que `AgendaController` ya hace.

## Implementación

`AppointmentController::create()` reutiliza `AgendaController::servicioFromQuery()` (misma lista de servicios activos que ya carga para el `<select>`, cero consultas nuevas) para resolver `servicio` y pasa solo su id a la vista. `create.blade.php` cambia `old('service_id')` por `old('service_id', $servicio ?? '')`: la preselección gana cuando no hay un valor antiguo (primera visita), pero una redisplay tras un error de validación con otro servicio elegido en el propio formulario sigue ganando — mismo orden de precedencia que ya usa `time`.

## Plan de pruebas

`tests/Feature/Admin/AgendaServiceFilterTest.php`: un `servicio` válido marca su `<option>` con `selected`; uno inválido o de un servicio inactivo no marca ninguno.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` · `docker compose exec -T node npm run build`.

## Fuera de alcance

El selector y el resaltado (T043, T044).
