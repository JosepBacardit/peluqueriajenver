# T037 — Tocar un hueco libre crea la cita

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-114
- **Depende de:** T036
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** retoma la funcionalidad pospuesta el 2026-10-05 («Crear una cita del panel a partir de un hueco libre»), aprobada ahora que la rejilla la hace natural; reutiliza `volver` y el patrón de parámetros ya validados de T029-T033.
- **Estado:** done
- **PR / rama:** `feature/agenda-timeline-grid`

## Objetivo

Que tocar un hueco libre de la rejilla (≥30 minutos) abra «Nueva cita» con la fecha, la hora exacta de ese hueco y `volver` ya puestos.

## Cambios

`resources/views/admin/agenda/_timeline-column.blade.php`: un segmento libre `tappable` pasa de `<div>` vacío a `<a href="{{ route('admin.appointments.create', ['fecha' => ..., 'hora' => ..., 'volver' => $volver]) }}">`, con `aria-label="Hueco libre a las HH:MM, plaza N"` (N = número de carril + 1, nunca el nombre de una peluquera). La hora prellenada es la del propio inicio del hueco (`startMinute` de `DayTimeline`), no redondeada.

`app/Http/Controllers/Admin/AppointmentController.php`: `create()` lee `hora` de la query con un nuevo `timeParam()` (expresión regular `HH:MM`, 00-23:00-59); un valor ausente o con formato inválido se descarta en silencio, igual que ya hacía `volverParam()`.

`resources/views/admin/appointments/create.blade.php`: el campo `time` usa `old('time', $time)` en vez de `old('time')`.

## Evidencia

`tests/Feature/Admin/AgendaTimelineGridTest.php` (nuevo): tocar un hueco libre lleva la fecha/hora/volver correctos y el `aria-label` «Hueco libre a las 09:00, plaza 1»/«plaza 2»; un hueco justo después de una cita prellena su hora exacta (no redondeada); un hueco de menos de 30 minutos no es un enlace; el formulario de crear prellena `time` con una `hora` válida y la ignora (campo vacío, sin caer) con una inválida (`99:99`, texto, sin los dos dígitos, con segundos).

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact --filter=AgendaTimelineGridTest` → 8 tests en verde (18 aserciones) · suite completa: **455 tests en verde** (1718 aserciones; partía de 447) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` → `{"result":"pass"}`.

Trampa encontrada y anotada en `AGENTS.md`: tras `php artisan view:clear`, el OPcache del contenedor `app` (`revalidate_freq=60`) puede seguir sirviendo el bytecode de una vista Blade compilada que ya no coincide con el archivo regenerado en disco, durante hasta 60 s — `docker compose restart app` (no el contenedor `node`) lo resuelve de forma fiable.

Pendiente a mano (lo hace el coordinador): comprobar en el navegador que tocar un hueco libre abre «Nueva cita» con los campos ya rellenos.
