# T043 — Selector de servicio y persistencia de `servicio`

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-120, PRF-123
- **Depende de:** T042
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Estado:** done
- **PR / rama:** `feature/agenda-service-filter`, desde `feature/agenda-timeline-grid`

## Objetivo

Un `<select>` de servicio encima de Día y Semana (no en Mes), y que el parámetro `servicio` sobreviva a cualquier navegación dentro de la agenda sin fundirse con `volver`. Solo el selector y su persistencia — el resaltado es T044, la preselección en «Nueva cita» es T045.

## Implementación

`AgendaController::index()` carga los servicios activos una vez (`Service::where('is_active', true)->ordered()->get()`), resuelve `servicio` contra esa misma lista (`servicioFromQuery()`, sin segunda consulta) y construye `$servicioQuery` (`['servicio' => $id]` o `[]`), repartido con `...$servicioQuery` en cada `route()` de `index.blade.php` (pestañas, Anterior/Siguiente/Hoy, «Nueva cita»), `_week.blade.php` (tira móvil) y `_month.blade.php` (celdas de día) — ningún sitio repite el literal `['servicio' => ...]`.

`volver` sigue exactamente igual (`"$vista:{$day->toDateString()}"`): `servicio` nunca entra en su formato, decisión explícita del usuario para no tocar `volverFromQuery()`.

El formulario de fecha y el nuevo formulario del selector llevan campos ocultos cruzados (`fecha`/`vista` en el del selector, `servicio` en el de fecha) para que cambiar uno no borre el otro. El `<select>` se envía solo (`onchange="this.form.submit()"`); el botón «Ver» es el respaldo sin JavaScript.

## Plan de pruebas

`tests/Feature/Admin/AgendaServiceFilterTest.php` (compartido con T044/T045): el selector lista «Cualquiera» y los servicios activos con su duración, nunca uno inactivo; no se muestra en Mes; un `servicio` inválido o de un servicio inactivo se ignora sin error; `servicio` y `volver` quedan en parámetros separados; sobrevive a las pestañas, Anterior/Siguiente/Hoy y a una visita a Mes.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` · `docker compose exec -T node npm run build`.

## Fuera de alcance

El resaltado «Cabe» (T044) y la preselección en «Nueva cita» (T045).
