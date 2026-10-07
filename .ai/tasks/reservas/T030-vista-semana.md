# T030 — Vista Semana

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-100, PRF-101, PRF-105, PRF-106, PRF-107
- **Depende de:** T029 (selector de vista y navegación)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `high`
- **Motivo:** nueva consulta agrupada por semana, dos plantillas (móvil y escritorio) y extracción de un parcial compartido con la vista Día; varios archivos, sin cambiar la concurrencia de escritura.
- **Estado:** done
- **PR / rama:** `feature/agenda-calendar-views`

## Objetivo

Añadir el contenido de la vista Semana: una tira de 7 días con la agenda del día elegido debajo en móvil (reutilizando la vista Día tal cual), y una rejilla de 7 columnas en escritorio.

## Cambios

`app/Http/Controllers/Admin/AgendaController.php`:
- `index()` ahora reparte por `$vista` con un `match`: `dayData($day)` (antes inline) para Día/Mes (Mes lo sustituye T031), `weekData($weekStart)` para Semana.
- `weekData()`: una consulta de `Appointment` en `[weekStart, weekStart+7 días)` y una de `ScheduleBlock::overlapping()` del mismo rango; agrupa en PHP en un array `days` (uno por día, con sus citas y cierres filtrados de las colecciones ya cargadas, nunca con una consulta nueva por día), más si ese día tiene horario (`openWeekdays()`, una consulta a `OpeningHour::distinct()`) y si es hoy.

`resources/views/admin/agenda/_day.blade.php` (nuevo): el contenido de citas/cierres del día, extraído sin cambios de `index.blade.php`, para que la vista Semana lo reutilice exactamente igual (mismos Llamar/WhatsApp/Editar/Cancelar).

`resources/views/admin/agenda/_week.blade.php` (nuevo):
- Móvil (`md:hidden`): tira de 7 días (`role="grid"`, zona táctil `min-h-11`, `aria-current="date"` en el seleccionado), cada pastilla con `aria-label` que dice si está cerrado o cuántas citas tiene (PRF-105/106 sin depender solo del color); debajo, `@include('admin.agenda._day', ...)` con las citas y cierres del día elegido.
- Escritorio (`hidden md:grid`): rejilla de 7 columnas (`role="grid"`), cada una con el nombre del día, "Cerrado" en texto si no hay horario, o la lista de citas (hora, servicio y clienta) ordenadas.

`resources/views/admin/agenda/index.blade.php`: el bloque de contenido pasa a `@if ($vista === 'semana') @include('admin.agenda._week') @else @include('admin.agenda._day') @endif` (Mes cae todavía en `_day` hasta T031).

## Evidencia

`tests/Feature/Admin/AgendaWeekViewTest.php` (nuevo): agrupa citas de lunes a domingo (y descarta las de otra semana); la tira móvil muestra la agenda completa del día elegido con sus botones de acción; los días sin horario se marcan "Cerrado"; la rejilla de escritorio lista el nombre del día y las citas por hora; Anterior/Siguiente mueven una semana entera; una consulta de `appointments` y una de `schedule_blocks` por semana (comprobado con `DB::enableQueryLog()`, nunca una por día); la tira cumple la zona táctil de 44 px.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact --filter="AgendaWeekViewTest|AgendaTest"` → 36 tests en verde (106 aserciones) · suite completa: **383 tests en verde** (1508 aserciones; partía de 376) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` → `{"result":"pass"}`.

Pendiente a mano (lo hace el coordinador): a 375 px, probar la tira de 7 días con el dedo y confirmar que no se corta; a 1024 px, confirmar que la rejilla de 7 columnas se lee bien y que un día cerrado se distingue sin mirar el color.
