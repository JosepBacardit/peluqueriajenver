# T041 — Resolver la revisión de la rejilla horaria de la agenda

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-109, PRF-112, PRF-119 (ampliados: cita forzada fuera de rango/sin horario, orden cronológico global, fecha en los `aria-label` de Semana)
- **Depende de:** T034–T040
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `high`
- **Motivo:** resuelve 1 hallazgo alto, 2 medios y 4 bajos de `.ai/reviews/agenda-timeline-grid.md`, más 6 hallazgos del coordinador en el navegador a 375 px (N1-N6), aprobados por el usuario, tocando el algoritmo de la rejilla (`DayTimeline`), el controlador y las cinco vistas de la agenda.
- **Estado:** done
- **PR / rama:** `feature/agenda-timeline-grid`

## Objetivo

Resolver, con la *skill* `fix-review`, los hallazgos de la revisión independiente de T034–T040 (`.ai/reviews/agenda-timeline-grid.md`) y los seis hallazgos adicionales que el coordinador vio en el navegador, aprobados por el usuario.

## Resolución de cada hallazgo

- **H1** (alto). Una cita confirmada fuera del rango de la rejilla seguía ocupando un carril invisible. `DayTimeline::extendBounds()` amplía el rango compartido (del día, o de toda la semana en Semana) para cubrir cualquier cita confirmada fuera de hora; el tramo que antes era una banda ciega pasa por la misma lógica de superviviente que un cierre real.
- **M1** (medio). Un día sin ningún tramo de horario ocultaba cualquier cita forzada en él. Tratado como un cierre total sintético (misma lógica que H1): la cita sigue viéndose en su carril, con «Cerrado» alrededor.
- **M2** (medio). Los `aria-label` de Semana (carril, cita, hueco) no llevaban la fecha de su columna. `_week.blade.php` pasa un `ariaDateLabel` (p. ej. «miércoles 7») por columna, que `_timeline-column.blade.php` antepone a cada `aria-label`.
- **L1** (bajo). Las posiciones usaban `diffInMinutes()` (tiempo real transcurrido), desalineándose una hora los domingos de cambio de hora. Sustituido por minutos de reloj (`hora*60+minuto`), el mismo patrón que ya usaba `nowLineTop()`.
- **L2** (bajo). Un hueco libre ya pasado seguía siendo un enlace a «Nueva cita». `DayTimeline::build()` recibe `$now` y solo marca tocable un hueco cuyo inicio no haya pasado.
- **L3** (bajo). Un tramo «cierre parcial» corto no llevaba ningún `aria-label`. Ahora siempre lo lleva, independientemente de si el texto visible cabe.
- **L4** (bajo, a decisión). El DOM agrupaba primero por carril. El coordinador confirmó que PRF-119 pide orden cronológico **global**: la rejilla ahora se construye como una cuadrícula CSS (filas en «fr» por minuto, una columna por carril) y emite sus segmentos ya intercalados por hora, con un enlace «Saltar a las citas» al principio.
- **N1** (coordinador). Cada tramo libre era un solo enlace enorme. Ahora cada media hora libre de cada carril es su propio enlace de 44 px con su propia hora; un tramo que no empieza en una hora redonda recibe un relleno no tocable hasta la siguiente media hora (elegido sobre un enlace a la hora impar, para que todo enlace caiga siempre en :00/:30).
- **N2** (coordinador). Las líneas de hora/media hora solo se veían en la columna de horas. Ahora se dibujan en todo el ancho de los carriles (dos degradados apilados, el de la hora encima).
- **N3** (coordinador). Un bloque corto cortaba su segunda línea. Ahora es una sola línea truncada «HH:MM Clienta» (y el servicio, si cabe); el detalle completo sigue a un toque, en su tarjeta.
- **N4** (coordinador). La rejilla tenía su propio *scroll* interno, anidado en el de la página. Quitado: la rejilla ocupa su alto natural y la página se desplaza sola a «ahora»; la cabecera de días de Semana (escritorio) es `sticky`.
- **N5** (coordinador). La etiqueta de las 09:00 salía cortada y con poco contraste. Quitado el truco `top:-5px`; tamaño y contraste ya legibles (`text-xs`, `text-gray-300`).
- **N6** (coordinador). No se veía qué carril era cada plaza. Día muestra una cabecera discreta «Plaza 1 · Plaza 2»; Semana se apoya solo en el `aria-label` (columnas demasiado estrechas), como aceptó el coordinador.

## Cambios

`app/Booking/DayTimeline.php`: nuevo `extendBounds()` (H1); `build()` recibe `$now`; `closedGap()` unifica el tratamiento de «cerrado»/«fuera de horario» con supervivientes (H1, M1); minutos de reloj en vez de `diffInMinutes()` (L1); `gapSegments()` trocea en medias horas alineadas y comprueba `$now` (N1, L2); `openPiece()` emite una cuadrícula CSS con los segmentos en orden cronológico global (L4).

`app/Http/Controllers/Admin/AgendaController.php`: `dayData()`/`weekData()` llaman a `extendBounds()` y pasan `$now` a `build()`.

`resources/views/admin/agenda/_timeline-column.blade.php`: reescrita sobre CSS grid; degradados de hora/media hora (N2); una sola línea truncada en las citas (N3); `aria-label` siempre presente en cierre parcial (L3); `$ariaDateLabel` antepuesto a cada `aria-label` (M2).

`resources/views/admin/agenda/_timeline-hour-axis.blade.php`: etiqueta sin recorte, más grande y con más contraste (N5).

`resources/views/admin/agenda/_timeline.blade.php`: sin *scroll* interno, desplazamiento de página (N4); enlace «Saltar a las citas» (L4); cabecera «Plaza 1 · Plaza 2» (N6).

`resources/views/admin/agenda/_day.blade.php`: `id="citas-del-dia"` como destino del enlace «Saltar a las citas» (L4).

`resources/views/admin/agenda/_week.blade.php`: sin *scroll* interno, cabecera `sticky` (N4); `ariaDateLabel` por columna (M2).

`.ai/specs/reservas.md`: PRF-112 y PRF-119 ampliados (H1/M1 superviviente, orden cronológico global y el enlace «Saltar a las citas»).

## Evidencia

Tests nuevos: `DayTimelineTest` (`extendBounds` ×4), `DayTimelineBuildTest` (H1, M1, L1 ×2 — primavera y otoño —, L2 ×2, y la reescritura de todos los casos existentes al nuevo formato de `segments`/`lane` en vez de `laneSegments`), `AgendaTimelineGridTest` (N1 — recuento de enlaces y redondeo a la media hora —, L3, N3, *script* de desplazamiento de página), `AgendaWeekViewTest` (M2, recuento de `w-11 shrink-0`), `AgendaCalendarAccessibilityTest` (reescrita sin `role="group"`, con el `aria-label` de cada plaza).

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` → 482 tests, 1906 aserciones, verde · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` → sin cambios · `docker compose exec -T node npm run build` → sin errores; **no se ha reiniciado el contenedor `node`**; `docker compose restart app` sí, varias veces, por el OPcache.

Pendiente a mano (lo hace el coordinador): la lista «Para comprobar en el navegador» actualizada de `.ai/reviews/agenda-timeline-grid.md`.
