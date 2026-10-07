# T039 — Semana con la rejilla horaria

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-118
- **Depende de:** T036, T037, T038
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `high`
- **Motivo:** repite en 7 columnas la rejilla ya construida (T034-T038), reutilizando `_timeline-column.blade.php` tal cual; el riesgo está en el reparto por columna (fecha/volver propios de cada día), no en volver a calcular nada.
- **Estado:** done
- **PR / rama:** `feature/agenda-timeline-grid`

## Objetivo

Sustituir la rejilla de listas de Semana en escritorio (T030) por la misma rejilla horaria de Día, en 7 columnas con el eje de horas una sola vez. Semana en el móvil ya la tenía desde T036 (reutiliza `_day.blade.php` para el día elegido).

## Cambios

`resources/views/admin/agenda/_week.blade.php`:
- Toda la sección de escritorio (antes: rejilla de listas con `role="grid"`/`"row"`/`"gridcell"`, T030/T033) se sustituye por: una fila de cabecera (hueco del ancho del eje + los 7 nombres de día, con el ajuste de mes al cruzar de L3), y debajo el eje de horas una sola vez más las 7 columnas (`_timeline-column.blade.php` con `compact: true`), todo dentro de un contenedor desplazable (`max-height: 70vh`) con su propio `<script>` de desplazamiento a «ahora» (igual que Día, T038).
- Cada columna pasa explícitamente `day` y `volver` propios (`'semana:'.$d['date']->toDateString()`), en vez de heredar el `$day`/`$volver` de la tira móvil — si no, un hueco libre de cualquier columna habría creado la cita en el día seleccionado por la tira, no en el día de esa columna. Se encontró al razonar sobre el flujo de `@include`, antes de llegar a los tests.
- La cabecera (fila de nombres de día) sigue siendo la única parte realmente tabular, así que conserva `role="row"`/`"columnheader"`; el cuerpo horario ya no finge ser una rejilla ARIA tabular (no lo es): se apoya en enlaces reales y el orden cronológico del HTML, igual que Día. Una revisión de accesibilidad completa de toda la rejilla es el objetivo de T040.

`app/Booking/DayTimeline.php`, `_timeline-column.blade.php`: sin cambios de lógica; `_timeline-column.blade.php` ya admitía `$nowLineTop` opcional desde T038, reutilizado aquí por columna.

## Evidencia

`tests/Feature/Admin/AgendaWeekViewTest.php`: 5 tests reescritos para el nuevo diseño (ya no listas, bloques que enlazan a `#cita-{id}` en vez de editar directamente; una cancelada ya no ocupa ningún carril, solo sigue en la tarjeta) y 3 nuevos — el eje de horas no se repite por columna, un hueco libre de una columna que no es el día elegido crea en la fecha de esa columna (la trampa encontrada), y la línea de «ahora» solo aparece en la columna de hoy.

`tests/Feature/Admin/AgendaCalendarAccessibilityTest.php`: el recuento de `role="row"`/`"gridcell"` se actualiza al nuevo diseño (2 y 7, antes 3 y 14), con nota de que T040 revisa la accesibilidad completa.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact --filter="AgendaWeekViewTest|AgendaCalendarAccessibilityTest"` → 24 tests en verde (73 aserciones) · suite completa: **466 tests en verde** (1739 aserciones; partía de 463) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` → `{"result":"pass"}` · `docker compose exec -T node npm run build` → sin errores; **no se ha reiniciado el contenedor `node`**.

Pendiente a mano (lo hace el coordinador): comprobar en el navegador, a 1024 px, que las 7 columnas se leen bien y que tocar un hueco libre de una columna crea la cita en el día correcto.
