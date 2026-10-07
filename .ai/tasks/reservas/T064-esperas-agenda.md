# T064 — Agenda por tramos activos con la marca de espera

- **Tipo:** ARCHITECTURAL
- **Puntos de referencia:** PRF-159 (y PRF-109, PRF-110, PRF-124 ajustados)
- **Depende de:** T062
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `high`
- **Motivo:** cambia la unidad de la rejilla (de cita a tramo activo) en el asignador de carriles y en `DayTimeline`, con sus tests de accesibilidad y de consultas fijas.
- **Estado:** pending
- **PR / rama:** `feature/service-wait-times`.

## Objetivo

Que la agenda muestre el hueco que deja una espera como libre y se pueda tocar. Cada tramo de la cita va en una plaza, con preferencia por la del tramo anterior, y así se ve cuándo la termina otra peluquera.

## Plan

1. **Tests primero:**
   - `AppointmentLaneAssignerTest` (tramos, preferencia de plaza, sobre capacidad por tramo);
   - `DayTimelineBuildTest` (segmentos por tramo, marca de espera, «(1/2)»);
   - `DayTimelineServiceFitTest` (`markServiceFit` con los intervalos activos del candidato);
   - `AgendaTimelineGridTest` (`aria-label`/`title`, número fijo de consultas).
2. `AppointmentLaneAssigner`: asignar tramos activos (clave cita y tramo), ordenados por inicio, prefiriendo la plaza del tramo anterior. Con la capacidad respetada en cada momento, siempre cabe en tantas plazas como la capacidad (PRF-153).
3. `DayTimeline`: `laneSegments`, `closedGap`/`splitByClosure` y `markServiceFit` por tramos, y la marca «Espera · {clienta} hasta {hora}» en el hueco libre de la plaza del tramo anterior.
4. `_timeline-column.blade.php` y las tarjetas de `_day.blade.php`: «(1/2)», «(2/2)» y la espera en el texto.
5. `npm run build` y comprobación en Chrome a 360 px y en escritorio con el ejemplo de la spec.
