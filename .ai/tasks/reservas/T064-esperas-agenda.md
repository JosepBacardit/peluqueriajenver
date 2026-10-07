# T064 — Agenda por tramos activos con la marca de espera

- **Tipo:** ARCHITECTURAL
- **Puntos de referencia:** PRF-159 (y PRF-109, PRF-110, PRF-124 ajustados)
- **Depende de:** T062
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `high`
- **Motivo:** cambia la unidad de la rejilla (de cita a tramo activo) en el asignador de carriles y en `DayTimeline`, con sus tests de accesibilidad y de consultas fijas.
- **Estado:** done
- **PR / rama:** `feature/service-wait-times`.

## Objetivo

Que la agenda muestre el hueco que deja una espera como libre y se pueda tocar. Cada tramo de la cita va en una plaza, con preferencia por la del tramo anterior, y así se ve cuándo la termina otra peluquera.

## Resolución

- **`AppointmentLaneAssigner::assign()`:**
  - asigna **tramos activos** con la clave `"{id}:{tramo}"` (`key()`), ordenados por inicio;
  - prefiere la plaza del tramo anterior de la misma cita si está libre, y si no, toma la primera libre;
  - como recorre los tramos por hora de inicio, preferir una plaza libre no añade carriles: hacen falta tantos como el máximo de tramos simultáneos (PRF-153);
  - `overCapacity` va por tramo, y devuelve también `stretches`.
  - Además se corrige el estilo de Pint que tenía pendiente.
- **`DayTimeline`:**
  - `stretchesAndWaits()` pasa los tramos a minutos del día y calcula las esperas, con las plazas de los tramos vecinos;
  - `laneSegments()` dibuja un segmento por tramo, con `part`, `parts`, `stretchStart`, `stretchEnd` y `waitUntil`, y marca con `wait` (clienta y hora) los huecos libres que se solapan con una espera en esas plazas;
  - `markServiceFit()` acepta un `TimeProfile` y solo exige la plaza libre durante los tramos activos del servicio elegido;
  - `splitByClosure` sigue usando la cita entera, porque la clienta está en el salón.
- **`AgendaController`:** pasa el perfil a `markServiceFit()`.
- **`_timeline-column.blade.php`:**
  - bloques «HH:MM Ana (1/2)», con su `aria-label` y su `title` de tramo; una cita sin esperas no cambia;
  - en un hueco con espera que se puede tocar: «Espera · Ana hasta 11:15» si no hay «Cabe», con borde discontinuo y el dato también en el `aria-label`;
  - un trozo libre corto (que no se puede tocar) lleva la misma marca, oculta a los lectores de pantalla.
- **`_day.blade.php`:** duración con la espera incluida y línea «Espera: 10:30–11:15».
- **`mail/partials/appointment-services`** (solo con `showDuration`, es decir, en los correos al salón): duraciones con la espera incluida y línea «Espera: …».
- `Appointment::waitMinutes()`/`waitsLabel()` y `TimeProfile::waitIntervals()`.

**Corrección tras la comprobación del coordinador en el navegador (2026-10-07):**
- **Problema:** en la plaza 2 del ejemplo, el hueco de 10:45 a 11:15 mostraba «Espera · Ana Prueba hasta 11:15» dos veces, una por cada franja de 15 min.
- **Arreglo:** `laneSegments()` marca ahora `waitLabel` solo en la primera franja de cada hueco continuo con la misma espera, y la vista solo pinta el texto visible ahí. El `aria-label` de cada franja tocable y el `title` siguen en todas.
- **Tests:** «the wait mark is labelled once per continuous free run…» y «the agenda shows the visible wait mark once per run…».
- Comprobado de solo lectura con los datos locales del coordinador (14/10/2026): 10:45 con `waitLabel` y 11:00 sin él.

Tests: `tests/Feature/Booking/WaitTimesAgendaTest.php` (12) y `AppointmentLaneAssignerTest`, adaptado a las claves por tramo. Suite completa: 833 en verde. Pint pasado sobre los archivos tocados. Pendiente: comprobación en el navegador (coordinador).

## Plan original

1. **Tests primero:**
   - `AppointmentLaneAssignerTest` (tramos, preferencia de plaza, sobre capacidad por tramo);
   - `DayTimelineBuildTest` (segmentos por tramo, marca de espera, «(1/2)»);
   - `DayTimelineServiceFitTest` (`markServiceFit` con los intervalos activos del candidato);
   - `AgendaTimelineGridTest` (`aria-label`/`title`, número fijo de consultas).
2. `AppointmentLaneAssigner`: asignar tramos activos (clave cita y tramo), ordenados por inicio, prefiriendo la plaza del tramo anterior. Con la capacidad respetada en cada momento, siempre cabe en tantas plazas como la capacidad (PRF-153).
3. `DayTimeline`: `laneSegments`, `closedGap`/`splitByClosure` y `markServiceFit` por tramos, y la marca «Espera · {clienta} hasta {hora}» en el hueco libre de la plaza del tramo anterior.
4. `_timeline-column.blade.php` y las tarjetas de `_day.blade.php`: «(1/2)», «(2/2)» y la espera en el texto.
5. `npm run build` y comprobación en Chrome a 360 px y en escritorio con el ejemplo de la spec.
