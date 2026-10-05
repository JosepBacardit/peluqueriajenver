# Revisión independiente: rejilla horaria de la agenda, vistas Día/Semana (T034–T040)

- **Alcance:** `git diff feature/agenda-calendar-views...feature/agenda-timeline-grid` (8 commits: `e330593` T034, `832c00d` T035, `9240cda` T036, `f55c4dc` T037, `31a4429` T038, `1c36f4e` T039, `de6ea6d` T040, `06c31db` guardia N+1 añadida al cerrar), contrastado con `.ai/specs/reservas.md` (PRF-108 a PRF-120, CA-15) y `.ai/tasks/reservas/T034`–`T040`.
- **Skill:** `review` (`.claude/skills/review/SKILL.md`).
- **Fecha:** 2026-10-05.
- **Revisor:** Claude Sonnet 5, en un contexto limpio. No ha participado en la implementación.
- **Tests:** `docker compose exec -T -u www-data app php artisan test --compact` → reproducido: **469 pasados (1751 aserciones)**, un pasado más que los 468 que registra T040 (el commit `06c31db`, posterior al cierre de T040, añade la guardia N+1 explícita de T036). Único aviso: Pest no puede escribir su caché de resultados (permisos del volumen), sin efecto en el resultado.
- **Build:** `docker compose exec -T node npm run build` → reproducido: compila sin errores (solo el aviso informativo de tiempos del plugin de Tailwind). El contenedor `node` no se ha reiniciado.
- **Navegador:** no lo he comprobado yo (sin sesión de usuario en este contexto). Ver «Para comprobar en el navegador» al final; incluye además los puntos que T036–T040 ya habían dejado pendientes a mano.
- **Resolución (T041, 2026-10-05):** los 7 hallazgos de esta revisión (H1, M1, M2, L1-L4) y los 6 que el coordinador vio en el navegador a 375 px (N1-N6, añadidos abajo, aprobados por el usuario) están resueltos en `feature/agenda-timeline-grid`, commits `58364b4` (algoritmo: H1, M1, L1, L2) y `b932060` (vistas: L3, L4, N1-N6, M2). Detalle en cada hallazgo y en la sección «Hallazgos del coordinador en el navegador» más abajo. Suite completa reproducida tras la resolución: **482 pasados (1906 aserciones)**.

## Resumen

| Severidad | Nº |
| --- | --- |
| Critical | 0 |
| High | 1 |
| Medium | 2 |
| Low | 4 |

- H1. Una cita confirmada fuera del rango de la rejilla (`gridStart`/`gridEnd`) sigue consumiendo un carril en `AppointmentLaneAssigner`, sin aparecer nunca en la rejilla — puede marcar «Sobre capacidad» a otras citas sí visibles, sin ninguna pista de por qué.
- M1. Un día sin ningún tramo de horario (`OpeningHour` vacío) ignora por completo las citas forzadas (`Guardar igualmente`): solo se ve la banda «Cerrado», nunca la cita.
- M2. En Semana (escritorio), los `aria-label` de cada carril («Plaza N»), de cada cita y de cada hueco libre nunca llevan la fecha: idénticos en las 7 columnas, sin forma de distinguir el día para quien no ve la posición en pantalla.
- L1. Cambio de hora (DST) en marzo/octubre: las posiciones de citas y cierres usan `diffInMinutes()` (tiempo real transcurrido) en vez de minutos de reloj, desalineándose una hora justo el domingo del cambio — hoy inalcanzable en la práctica (el salón cierra domingo y choca con M1), pero queda latente.
- L2. Un hueco libre en el pasado sigue siendo un enlace a «Nueva cita»; el backend lo rechaza correctamente, pero solo después de rellenar todo el formulario.
- L3. Un tramo «cierre-parcial» de menos de 20 px de alto no muestra ningún texto y no lleva `aria-label`: queda completamente mudo para quien no lo ve (ni lector de pantalla, ni foco, solo el patrón de rayas).
- L4. El orden del DOM agrupa primero por carril («Plaza 1» completa, luego «Plaza 2» completa) y es cronológico solo dentro de cada carril, no de forma global entre carriles — a decidir si satisface la letra de PRF-119 («en orden cronológico en el HTML»).

## Comprobaciones sin hallazgos

- **Algoritmo de carriles — solapes, encadenados y estabilidad.** `AppointmentLaneAssignerTest.php` cubre citas que se solapan, que terminan justo cuando otra empieza (comparten carril, no se tratan como solape), que liberan y reutilizan un carril, y que el resultado no depende del orden de entrada (`assignment does not depend on input order`). Reproducido en la ejecución completa.
- **Capacidad 1 y 3.** El algoritmo (`AppointmentLaneAssigner::assign()`) no tiene ningún valor de capacidad fijado en el código (ni `2` ni ningún otro): `maxLanes = max($capacity, carriles usados)` funciona igual para cualquier entero positivo. No hay un test con capacidad 1 o 3, pero la lógica no depende de un valor concreto — ver sin embargo H1 para el caso de citas fuera de rango, que sí es un hallazgo real del algoritmo.
- **Citas canceladas.** Tanto `AppointmentLaneAssigner::assign()` (`a cancelled appointment is left out of the lane assignment entirely`) como `DayTimeline::build()` (`a cancelled appointment does not occupy a lane`) filtran por `isConfirmed()` antes de nada; la cita cancelada sigue viéndose atenuada en la lista de tarjetas de debajo.
- **Minutos a píxeles, sin deriva.** `DayTimeline::pxFromMinutes()` usa `round()` por posición, nunca por duración; `DayTimelineTest.php` comprueba explícitamente que `pxFromMinutes(615) - pxFromMinutes(600)` da 22 (no 23 por redondeo independiente), y todo segmento del árbol de construcción (`band()`, `openPiece()`, `gapSegments()`) calcula su alto como diferencia de dos llamadas, nunca `round()` de la duración aislada.
- **Pausa de mediodía / tramo cerrado cruzado por una cita.** `DayTimelineBuildTest.php` cubre explícitamente: hueco entre dos tramos («fuera de horario»), un cierre total sin cita (banda limpia), un cierre total con una cita que sobrevive (PRF-022: la cita se sigue viendo, el resto se sombrea «cierre-parcial»), el mismo caso cuando la cita termina antes que el cierre, y un cierre parcial que solo sombrea el carril por encima de la capacidad reducida.
- **Rango cuando no hay horario.** `DayTimeline::weekBounds()` cae a 09:00–19:00 cuando `OpeningHour` está vacía (`weekBounds falls back to a default range...`), evitando la división por cero.
- **`hora` en `AppointmentController::create()`.** `timeParam()` (`app/Http/Controllers/Admin/AppointmentController.php:49-52`) exige `^([01]\d|2[0-3]):[0-5]\d$`, exactamente el formato que genera `_timeline-column.blade.php:54` (`sprintf('%02d:%02d', ...)`); un valor ausente o malformado se descarta en silencio (`AgendaTimelineGridTest.php`, casos `99:99`, texto, `9:00`, con segundos — los cuatro reproducidos).
- **CSS sin capa.** No hay ningún `<style>` nuevo ni CSS fuera de las utilidades de Tailwind en todo el diff (`grep` sobre `resources/views/admin/agenda/` y `resources/css/` no encuentra ninguno): toda la maquetación usa clases de Tailwind más `style=""` inline (que siempre gana a cualquier utilidad), así que el problema de cascada de la lección anterior (fuentes) no se repite aquí.
- **`position: absolute` en contenedor con scroll.** La línea de «ahora» (`_timeline-now-line.blade.php`) es `absolute` dentro de la columna del día (`position: relative`, en flujo normal dentro de `#timeline-scroll`/`#week-timeline-scroll`, que son los que llevan `overflow-y-auto`), no directamente dentro del contenedor con scroll: su `top` se mide desde el borde de la columna, que sí forma parte del contenido desplazable, así que se desplaza junto con el resto.
- **Trampas de entorno documentadas en `AGENTS.md`.** Las dos anotadas por el agente son correctas: OPcache del contenedor `app` (`revalidate_freq=60`, `docker/php/opcache.ini`) puede servir bytecode de una vista Blade ya regenerada hasta 60 s, y `docker compose restart app` (no `node`) es el único arreglo fiable — confirmado en `docker/php/opcache.ini` y en que ninguna vista de este diff necesitó ese reinicio durante la revisión porque ya estaba corriendo desde antes; la trampa de `@endif` pegado a texto (T037) no deja ningún rastro en el código actual: cada `@endif`/`@endforeach`/`@endunless` de `_timeline-column.blade.php` está en su propia línea.
- **N+1.** `AgendaTimelineGridTest.php` › «vista Día loads the timeline grid with a fixed number of queries, not one per appointment» prueba 1 sola consulta a `appointments`, `schedule_blocks`, `opening_hours` y `booking_settings` con 10 citas en el día; reproducido en la ejecución completa (469 pasados).
- **Semana: `day`/`volver` por columna.** `_week.blade.php:102` pasa `day`/`volver` explícitos a cada columna (`'semana:'.$d['date']->toDateString()`), evitando que un hueco libre de cualquier columna cree la cita en el día de la tira móvil en vez de en el de su propia columna — la trampa que el propio T039 documenta haber encontrado por razonamiento antes de los tests. `AgendaWeekViewTest.php` lo cubre.

---

## High

### H1. Una cita fuera del rango de la rejilla sigue contando para la asignación de carriles, sin aparecer nunca en pantalla

- **Estado:** resuelto (T041, commit `58364b4`).
- **Resolución:** nuevo `DayTimeline::extendBounds(int $gridStart, int $gridEnd, Collection $appointments): array`, llamado desde `AgendaController::dayData()`/`weekData()` justo después de `weekBounds()`: recorre las citas confirmadas y amplía `$gridStart`/`$gridEnd` (redondeado a la hora, igual que `weekBounds()`) para cubrir cualquiera que caiga fuera del rango normal. En Semana se amplía el rango **compartido** de las 7 columnas con las citas de toda la semana (cada cita aporta su propio día de referencia, así que no se mezclan minutos de un día con los de otro). Una vez ampliado el rango, el tramo que antes era una banda "fuera de horario" ciega pasa por `closedGap()` (la misma función que resuelve M1): la cita sobrevive en su carril, con el resto del tramo banda normal alrededor. Test: `DayTimelineBuildTest` («a confirmed appointment outside this day's own hours still gets a lane once the grid is extended to cover it») y 4 tests de `extendBounds()` en `DayTimelineTest` (amplía el fin, amplía el inicio, ignora una cita cancelada, no cambia nada si todo ya cabe).
- **Evidencia:**
  - `app/Booking/DayTimeline.php:137-146`: `$confirmed` (todas las citas confirmadas del día, sin filtrar por `$gridStart`/`$gridEnd`) se pasa entera a `AppointmentLaneAssigner::assign($confirmed, $capacity)`.
  - `app/Booking/DayTimeline.php:300-309` (dentro de `openPiece()`): cada carril solo pinta las citas que solapan la pieza actual (`$aStart < $end && $aEnd > $start`), y las piezas solo cubren `[$gridStart, $gridEnd]`. Una cita cuyo `starts_at`/`ends_at` caiga entera fuera de ese rango no solapa ninguna pieza, así que no se pinta en ningún carril — pero ya ocupó un carril en la asignación global del paso anterior.
  - Esto es alcanzable con la funcionalidad ya existente, documentada en `AGENTS.md` («el salón mueve una cita... Guardar igualmente... solo desde ahí»): `RescheduleAppointment::handle()` con `ignoreHoursAndCapacity: true` salta entero `AvailabilityCalculator::unavailabilityReason()` (`app/Actions/RescheduleAppointment.php:122-123`), que es la única comprobación de horario — solo queda el guardia de `StartTimeInPastException`. Un admin puede, hoy, mover una cita confirmada a cualquier hora futura, incluida una fuera de la apertura más temprana/cierre más tardío de toda la semana (`gridStart`/`gridEnd`).
  - No hay ningún test que cubra una cita cuyo inicio o fin caiga fuera de `[$gridStart, $gridEnd]` (revisado `DayTimelineBuildTest.php` completo: todas sus citas de prueba caen dentro del rango 09:00-19:00 del *fixture*).
- **Impacto:** si existe una de estas citas «fantasma» (fuera de rango) el mismo día que otras citas sí visibles y simultáneas a capacidad, estas últimas pueden terminar marcadas «Sobre capacidad» (o la rejilla pintar un carril extra) sin que la cita que realmente lo provoca se vea en ningún sitio de la rejilla — solo en la lista de tarjetas de debajo, con una hora que no coincide con ninguna columna visible. El salón vería una advertencia de sobrecapacidad sin ninguna pista visual de su causa, lo que socava justo lo que PRF-109 pide mostrar.
- **Recomendación:** filtrar `$confirmed` a las citas que solapan `[$gridStart, $gridEnd]` antes de pasarlo a `AppointmentLaneAssigner::assign()` (`DayTimeline.php:137` o justo antes de la línea 146), igual que ya se filtra por `isConfirmed()`. Si se decide que una cita fuera de rango debe seguir «contando» a efectos de capacidad aunque no se pinte, documentarlo explícitamente y, aun así, no dejarla invisible: considerar una nota en la banda de fuera-de-horario o en el encabezado del día («+1 fuera de rango») en vez de un silencio total. Añadir un test con una cita cuyo inicio sea anterior a `$gridStart` (o cuyo fin sea posterior a `$gridEnd`) junto a otras dentro de rango, comprobando que no se cuenta para `overCapacity` de las que sí se ven.

---

## Medium

### M1. Un día totalmente cerrado (sin ningún tramo) oculta cualquier cita forzada en él

- **Estado:** resuelto (T041, commit `58364b4`).
- **Resolución:** `DayTimeline::build()` ya no tiene un retorno anticipado para `$ranges->isEmpty()`; ese caso (y, de forma simétrica, cada tramo "fuera de horario" del día, por H1) pasa ahora por `closedGap()`, una función nueva que trata el hueco como un cierre total sintético (un bloque con `reduction: null` cubriendo todo el tramo) y lo divide con la misma `splitByClosure()` que ya usa un `ScheduleBlock` real: si ninguna cita confirmada sobrevive dentro, sale una única banda ("Cerrado" o "Fuera de horario" según el caso); si alguna sobrevive, el sub-tramo se abre con `openPiece()` y la cita se ve en su carril, con el resto bandeado alrededor. Test: `DayTimelineBuildTest` («a day with no opening-hours range still shows a forced appointment, shading the other lane "cerrado"»).
- **Evidencia:**
  - `app/Booking/DayTimeline.php:126-131`:
    ```php
    if ($ranges->isEmpty()) {
        return [
            'lanes' => $capacity,
            'pieces' => [self::band('cerrado', $gridStart, $gridEnd, $gridStart)],
        ];
    }
    ```
    Este retorno anticipado ignora por completo el parámetro `$appointments` — a diferencia del caso simétrico ya resuelto para un cierre puntual (`ScheduleBlock`) con una cita superviviente (PRF-022), cubierto explícitamente por el test «a full closure with a surviving appointment keeps showing the appointment, shading the other lane» (`DayTimelineBuildTest.php:155-169`). Aquí no hay ningún test equivalente para un día sin tramos de horario semanal.
  - Alcanzable por el mismo mecanismo que H1: `RescheduleAppointment` con `ignoreHoursAndCapacity: true` permite mover una cita a un día sin ningún `OpeningHour` (por ejemplo, domingo o lunes en el horario por defecto martes-sábado).
- **Impacto:** una cita forzada en un día cerrado solo se ve en la lista de tarjetas de debajo de la rejilla, nunca en la propia rejilla (que siempre muestra «Cerrado» ocupando todo el ancho) — inconsistente con cómo sí se trata el mismo escenario para un cierre puntual, y contrario a la intención de PRF-109/110 de que toda cita confirmada tenga un carril visible.
- **Recomendación:** antes del retorno anticipado de la línea 126, comprobar si `$appointments` tiene alguna cita confirmada; si la hay, tratar el día como una única pieza abierta (igual que el cierre puntual con superviviente) en vez de una banda «Cerrado» ciega. Añadir un test: día sin `OpeningHour` para ese `weekday`, con una cita confirmada dentro del rango de la rejilla — debe verse en su carril, no solo la banda «Cerrado».

### M2. En Semana (escritorio), ningún `aria-label` de la rejilla lleva la fecha de su columna

- **Estado:** resuelto (T041, commit `b932060`).
- **Resolución:** `_week.blade.php` pasa un nuevo `ariaDateLabel` (p. ej. `"miércoles 7"`, con el mismo array `$weekdays` que ya usa la cabecera) a cada `@include('admin.agenda._timeline-column', ...)`, una vez por columna. `_timeline-column.blade.php` lo antepone (`"{$ariaDateLabel}, "`) a los tres `aria-label` (carril/plaza, cita, hueco libre); en Día, donde no se pasa `ariaDateLabel`, el `aria-label` queda exactamente igual que antes. Nota: por L4 (más abajo), cada carril ya no es un `role="group"` contiguo, así que el "plaza N" se trasladó al `aria-label` de cada cita/hueco/cierre-parcial individual en vez de a un envoltorio por carril — M2 se resolvió sobre ese nuevo formato. Test: `AgendaWeekViewTest` («the week view desktop grid links each appointment block to its card below», que comprueba el `aria-label` completo con fecha y plaza).
- **Evidencia:**
  - `resources/views/admin/agenda/_timeline-column.blade.php:28` — `aria-label="Plaza {{ $lane + 1 }}"`, idéntico en las 7 columnas.
  - `resources/views/admin/agenda/_timeline-column.blade.php:38` — `aria-label="{{ $appointment->starts_at->format('H:i') }} {{ $appointment->service_name }}, {{ $appointment->customer_name }}..."`, solo la hora, nunca la fecha.
  - `resources/views/admin/agenda/_timeline-column.blade.php:63` — `aria-label="Hueco libre a las {{ $slotTime }}, plaza {{ $lane + 1 }}"`, mismo problema.
  - `resources/views/admin/agenda/_week.blade.php:96-104` incluye esta misma plantilla, sin cambios, una vez por cada una de las 7 columnas; la única fecha visible está en la cabecera de columna (`_week.blade.php:82-83`), separada del cuerpo horario y sin ninguna asociación programática (`aria-describedby`, `headers`/`id`, o similar) que la ligue a los `role="group"`/enlaces de esa columna.
  - El `role="region"` que envuelve toda la semana (`_week.blade.php:67`) solo dice el rango de fechas de toda la semana, no por columna.
- **Impacto:** alguien que navegue con teclado o lector de pantalla y llegue, por ejemplo, a «Plaza 1» o a «09:00 Corte, Fulanita» en medio de las 7 columnas no tiene ninguna forma de saber a qué día pertenece ese grupo o esa cita salvo contando cuántos elementos ha recorrido — una persona que ve la pantalla lo sabe por la posición horizontal bajo la cabecera del día, pero quien usa un lector de pantalla no. Esto contradice el espíritu de PRF-119 («cada hueco libre y cada bloque de cita... con `aria-label` completo») en el contexto específico de Semana, donde «completo» debería incluir el día.
- **Recomendación:** pasar la fecha de la columna al `aria-label` de cada carril (`"Plaza N, {fecha corta}"`) y de cada cita/hueco libre (añadir la fecha cuando la columna no es la de hoy, o siempre, para consistencia), o envolver cada columna de `_week.blade.php` en su propio `role="group"`/`region` con `aria-label` de la fecha completa del día, del que los carriles y enlaces internos hereden el contexto al navegarlos. Confirmar con un test que el `aria-label` de una cita/hueco libre en una columna que no es la de hoy incluye su fecha.

---

## Low

### L1. Cambio de hora (DST): las posiciones usan tiempo real transcurrido, no minutos de reloj

- **Estado:** resuelto (T041, commit `58364b4`).
- **Resolución:** nuevo `DayTimeline::minutesSinceDayStart()` (privado), que calcula minutos de reloj (`hora*60+minuto`, igual que ya hacía `nowLineTop()`) más 1440 por cada día de calendario completo de diferencia — ese número de días se calcula a partir de las fechas `Y-m-d` por separado, convertidas a un `DateTime` en UTC (donde no hay cambio de hora), nunca con `diffInMinutes()`/`diffInDays()` reales sobre las fechas con huso horario. Sustituye a los 4 usos de `diffInMinutes()` señalados (intervalos de citas y de cierres, y dentro de `openPiece()`/`laneSegments()`). Test: dos casos en `DayTimelineBuildTest`, uno para el domingo de marzo (2026-03-29) y otro para el de octubre (2026-10-25), comprobando que una cita a las 10:00 sigue dando 60 minutos desde la apertura de las 09:00 en ambos.
- **Evidencia:**
  - `app/Booking/DayTimeline.php:138-141` (intervalos de citas) y `:199-200` (intervalos de cierres) calculan la posición con `$dayStart->diffInMinutes($a->starts_at)` / `diffInMinutes($end)` — `diffInMinutes()` de Carbon mide el tiempo real transcurrido entre dos instantes, no la diferencia de "reloj de pared", así que en un día con cambio de hora da un resultado distinto del número de minutos que marca el reloj.
  - Comprobado en vivo con Tinker (sin escribir en base de datos), con `config('app.timezone') = 'Europe/Madrid'`:
    ```
    $dayStart = CarbonImmutable::parse("2026-03-29 00:00:00", "Europe/Madrid");
    $apt = CarbonImmutable::parse("2026-03-29 10:00:00", "Europe/Madrid");
    $dayStart->diffInMinutes($apt); // 540 (debería ser 600: 10:00 son 600 minutos de reloj desde medianoche)

    $dayStart2 = CarbonImmutable::parse("2026-10-25 00:00:00", "Europe/Madrid");
    $apt2 = CarbonImmutable::parse("2026-10-25 10:00:00", "Europe/Madrid");
    $dayStart2->diffInMinutes($apt2); // 660 (debería ser 600)
    ```
    2026-03-29 y 2026-10-25 son los domingos de cambio de hora en España. Una cita a las 10:00 se posicionaría en la rejilla como si fuera a las 9:00 (marzo) o a las 11:00 (octubre) — una hora de desfase respecto al eje de horas, que sí se calcula correctamente porque `pxFromMinutes()` no usa fechas.
  - En cambio, `nowLineTop()` (`DayTimeline.php:83-96`) sí calcula bien: usa `$now->hour * 60 + $now->minute` directamente, no `diffInMinutes()`, así que la línea de «ahora» no tiene este problema — es la prueba de que el patrón correcto ya existe en el propio archivo, solo que no se usó también en `build()`.
  - **Por qué hoy es inalcanzable en la práctica:** el cambio de hora cae siempre en domingo, y el horario por defecto del salón es martes-sábado (domingo cerrado, sin ningún `OpeningHour`). Eso significa que `DayTimeline::build()` para ese domingo entra por el retorno anticipado de M1 (`$ranges->isEmpty()`), que nunca llega a calcular `appointmentIntervals`/`blockIntervals` — así que el código defectuoso ni se ejecuta hoy, salvo que M1 se corrija (entonces sí se ejecutaría) o que el salón abra alguna vez en domingo.
- **Impacto:** ninguno mientras domingo siga cerrado y M1 sin resolver; pasaría a ser un desfase real de una hora, exactamente esos dos domingos al año, en cuanto cualquiera de esas dos condiciones cambie.
- **Recomendación:** sustituir `$dayStart->diffInMinutes(...)` por el mismo patrón que ya usa `nowLineTop()` (extraer hora y minuto de reloj y multiplicar/sumar), en las dos líneas señaladas. Añadir un test con `CarbonImmutable::parse('2026-03-29 10:00', 'Europe/Madrid')` (o el domingo de octubre) comprobando que la posición da 600 minutos desde medianoche, no 540/660.

### L2. Un hueco libre en el pasado sigue siendo un enlace a «Nueva cita»

- **Estado:** resuelto (T041, commit `58364b4`); decisión tomada: ya no es tocable, pero sigue viéndose.
- **Resolución:** `DayTimeline::build()` recibe un nuevo parámetro `$now` (pasado por `AgendaController` como `CarbonImmutable::now()`, el mismo valor que ya usaba para `nowLineTop()`), convertido una vez a minutos-desde-la-medianoche-de-este-día con el mismo `minutesSinceDayStart()` de L1. `gapSegments()` solo marca `tappable` un tramo libre de 30 minutos cuando además su inicio es `>= $nowMinute` — para un día futuro, `$nowMinute` sale muy por debajo de cualquier minuto del día (siempre tocable); para un día ya pasado, muy por encima (nunca tocable); para hoy, la comparación es la hora real. El hueco pasado sigue viéndose en su sitio, solo que sin enlace (igual que ya pasaba con los huecos de menos de 30 minutos). Test: dos casos en `DayTimelineBuildTest` («a free half hour before "ahora" today is not tappable, one after it is» y «every free half hour of a fully past day is not tappable»).
- **Evidencia:**
  - `app/Booking/DayTimeline.php:357-390` (`gapSegments()`): `tappable` se decide solo por duración (`>= self::MIN_TAPPABLE_MINUTES`), sin comparar con «ahora» en ningún momento — ni aquí ni en `build()`/`openPiece()`.
  - El backend sí rechaza correctamente una hora pasada: `AvailabilityCalculator::unavailabilityReason()` (`app/Booking/AvailabilityCalculator.php:107-109`) comprueba `$start->lt($now)` antes que cualquier otra regla, incluso con `$applyPublicRules = false` (panel). `AppointmentController::store()` captura `SlotUnavailableException` y devuelve el error «Esa hora no está disponible para este servicio.» (`AppointmentController.php:61-62`).
- **Impacto:** al ver el día de hoy (antes de la hora actual) o cualquier día ya pasado, todos los huecos libres de 30 minutos o más siguen ofreciéndose como enlaces táctiles a «Nueva cita». Tocar uno de esos lleva al formulario completo, con fecha/hora ya puestas, y solo al enviarlo aparece el error — un viaje de ida y vuelta innecesario, sin ninguna pista antes de rellenar el formulario de que esa hora ya no es reservable.
- **Recomendación:** decisión del usuario: o bien `DayTimeline` deja de marcar `tappable` un hueco cuyo inicio ya pasó (comparando contra `$now`, que ya se pasa a `build()`/`nowLineTop()` indirectamente vía el controlador), o se acepta el comportamiento actual (el backend ya protege la integridad de los datos) y se documenta como conocido. Si se corrige, un hueco pasado podría seguir viéndose (para que el salón revise lo ocurrido) pero sin ser un enlace, igual que ya se hace con los huecos de menos de 30 minutos.

### L3. Un tramo «cierre-parcial» de menos de 20 px no transmite nada a quien no lo ve

- **Estado:** resuelto (T041, commit `b932060`).
- **Resolución:** el `<div>` de `cierre-parcial` en `_timeline-column.blade.php` ahora lleva siempre `aria-label="{{ fecha, si aplica }}Cierre parcial, plaza {{ carril }}"` y `title="Cierre parcial"`, independientemente de su altura; el texto visible («Cierre») sigue condicionado a los 20 px (motivo de maquetación, no de accesibilidad). Test: `AgendaTimelineGridTest` («a short partial closure still has an aria-label, even with no visible text», con un `ScheduleBlock` de 10 minutos).
- **Evidencia:** `resources/views/admin/agenda/_timeline-column.blade.php:45-51`:
    ```blade
    @elseif ($segment['type'] === 'cierre-parcial')
        <div class="flex items-center justify-center text-[9px] leading-none text-amber-200"
             style="height: {{ $segment['height'] }}px; background-image: repeating-linear-gradient(...)">
            @if ($segment['height'] >= 20)
                Cierre
            @endif
        </div>
    ```
    Con 88 px/hora, 20 px son unos 13,6 minutos: un cierre de capacidad reducida más corto que eso (un `ScheduleBlock` de, por ejemplo, 10 minutos) renderiza un `<div>` sin texto, sin `aria-label`, sin `role` ni ningún otro atributo accesible — comparar con las bandas de ancho completo (`cerrado`/`fuera-horario`/`cierre`, línea 19), que muestran su texto siempre, sin ninguna condición de altura.
- **Impacto:** quien no ve la pantalla (lector de pantalla) no tiene ninguna señal de que ese hueco está sombreado por un cierre parcial — ni texto ni `aria-label` —, y quien sí la ve pero no distingue bien el patrón de rayas sobre un fondo oscuro a 9px tampoco tiene una alternativa textual. Caso límite (depende de que exista un cierre parcial de menos de ~14 minutos), pero sin ninguna protección.
- **Recomendación:** añadir siempre un `aria-label="Cierre parcial"` (o similar) al contenedor, independientemente de si el texto visible cabe o no; si el texto visible sigue condicionado a la altura por motivos de maquetación, que al menos quede en el DOM para lectores de pantalla (por ejemplo, con una clase `sr-only` en vez de omitirlo del todo).

### L4. El orden del DOM es cronológico dentro de cada carril, no entre carriles — a decidir si cumple PRF-119

- **Estado:** resuelto (T041, commit `b932060`); el coordinador confirmó que PRF-119 pide orden cronológico global, no por carril.
- **Resolución:** `DayTimeline::openPiece()` ya no agrupa por carril en flujo normal: construye un conjunto de filas CSS Grid a partir de los límites de minuto de **todos** los carriles del tramo (sin deriva, en unidades `fr` — igual que `pxFromMinutes()` evita la deriva de redondeo, pero aquí con división real de CSS en vez de redondeo entero), coloca cada segmento con `grid-row`/`grid-column` explícitos y devuelve `segments` ya en una única lista ordenada por minuto de inicio y, como criterio de empate, por carril — ese es el orden en el que `_timeline-column.blade.php` los emite en el DOM. Se añadió además un enlace «Saltar a las citas» al principio de la rejilla de Día/Semana-móvil (`_timeline.blade.php`), con destino `#citas-del-dia` en `_day.blade.php`, para no obligar a tabular por decenas de huecos libres antes de llegar a las tarjetas. Efecto secundario aceptado: como los carriles ya no son contiguos en el DOM, dejó de tener sentido envolverlos en un único `role="group"` por carril (M2 lo resolvió trasladando el "plaza N" al `aria-label` de cada cita/hueco individual en su lugar). `.ai/specs/reservas.md` PRF-119 se amplió para decir "orden cronológico global" explícitamente. Test: `DayTimelineBuildTest` verifica el contenido de `segments`/`lane` en cada caso; `AgendaCalendarAccessibilityTest` y `AgendaTimelineGridTest` comprueban el `aria-label` de cada plaza. El orden global del DOM no tiene un test automático dedicado (queda demostrado por construcción: `segments` sale ya ordenado del `usort` en `openPiece()`, y es el mismo array que recorre el `@foreach` de la vista); conviene que el coordinador lo confirme tabulando a mano (ver «Para comprobar en el navegador»).
- **Evidencia:** `resources/views/admin/agenda/_timeline-column.blade.php:23-28`: el bucle externo recorre `$piece['laneSegments']` por carril (`Plaza 1` completa de arriba a abajo, luego `Plaza 2` completa), y solo dentro de cada carril los segmentos están en orden cronológico (así los construye `DayTimeline::openPiece()`, `app/Booking/DayTimeline.php:299-338`, recorriendo cada carril por separado). Con dos citas simultáneas en carriles distintos a las 09:00 y 11:00 en «Plaza 1» y una a las 09:30 en «Plaza 2», el orden de tabulación sería: hueco, 09:00 (Plaza 1), hueco, 11:00 (Plaza 1), hueco final (Plaza 1) — luego hueco, 09:30 (Plaza 2), hueco (Plaza 2): la cita de las 09:30 llega al teclado *después* de la de las 11:00, aunque sea anterior en el reloj.
  - PRF-108 a PRF-120 (`.ai/specs/reservas.md:270`) dice literalmente: «La rejilla debe ser navegable por teclado, con cada hueco libre y cada bloque de cita como enlace real, **en orden cronológico en el HTML**». La matriz de pruebas (`.ai/specs/reservas.md:411`) repite «en orden cronológico» sin matizar si es dentro de cada carril o global.
  - T040 (`.ai/tasks/reservas/T040-accesibilidad-rejilla.md:15-17`) ya razona sobre esto («no se ha construido una lista de texto redundante... si la comprobación con lector de pantalla pendiente encontrara que la rejilla no se lee bien por sí sola, se añadiría entonces»), pero no menciona este desajuste concreto entre orden global y orden por carril.
- **Impacto:** depende de cómo se interprete PRF-119. Agrupar por «Plaza» (carril) es razonable y es justo lo que motivó el `role="group"` de T040; pero si la intención literal de PRF-119 era un orden cronológico global (como si se leyera "la agenda completa, cita a cita, en el orden en que ocurren"), el diseño actual no lo cumple en cuanto hay más de un carril ocupado con solapes.
- **Recomendación:** pedir al usuario que confirme si el orden por carril (actual) satisface PRF-119, o si se espera un orden global cronológico entrelazando carriles (lo que complicaría la maquetación en flujo normal sin posicionamiento absoluto, ver T036). Si se acepta el orden actual, ajustar la redacción de PRF-119/su fila en la matriz para decirlo explícitamente («cronológico dentro de cada carril»), evitando la ambigüedad para la próxima persona que lea la especificación.

---

## Hallazgos del coordinador en el navegador (N1-N6, 2026-10-05)

Vistos en Chrome a 375 px, con la sesión del usuario; **aprobados por el usuario** y resueltos en T041 (commit `b932060`).

### N1. Cada tramo libre de cada plaza era un solo enlace enorme

- **Estado:** resuelto.
- **Resolución:** `DayTimeline::gapSegments()` ahora trocea cada tramo libre por cada marca de media hora absoluta, además de por los límites de cierre parcial; cada trozo de exactamente 30 minutos alineado a `:00`/`:30` es su propio enlace, con su propia hora (`startMinute`). Un tramo que no empieza en una hora redonda (por ejemplo, tras una cita que termina a las 11:55) recibe un relleno no tocable hasta la siguiente marca — elegido sobre la alternativa (un enlace a la hora impar) para que todo enlace de la rejilla caiga siempre en `:00`/`:30`, documentado en el docblock de `gapSegments()`. Test añadido como pedía el coordinador: `AgendaTimelineGridTest` («there is exactly one link per free half hour of each lane, with its own hour») comprueba que el número de enlaces coincide con las medias horas libres (40 = 20 medias horas × 2 plazas, en un día de 09:00 a 19:00 sin citas) y que cada uno lleva su hora; otro test comprueba el relleno no tocable tras una cita a las 09:00-09:35.

### N2. Las líneas de hora/media hora solo se veían en la columna de las horas

- **Estado:** resuelto.
- **Resolución:** el contenedor CSS grid de cada tramo abierto (`_timeline-column.blade.php`) lleva ahora dos fondos `linear-gradient` apilados (`background-size: 100% 88px, 100% 44px`), el de la hora listado primero para que gane visualmente donde ambos coinciden, con `background-position-y` desplazado según el `top` del propio tramo módulo cada periodo, para que las líneas caigan en las mismas marcas absolutas de minuto en cualquier tramo. Sin test automático (es un efecto puramente visual); a comprobar en el navegador (ver más abajo).

### N3. Un bloque de 15 minutos (22 px) cortaba su segunda línea

- **Estado:** resuelto.
- **Resolución:** el bloque de cita pasa a una sola línea truncada (`class="truncate"`), `"HH:MM Clienta"` (y el servicio, si `$compact` es `false` y cabe), sin la línea de «Sobre capacidad» por debajo cuando no hay sitio — su detalle completo sigue en el `title`/`aria-label` y a un toque, en su tarjeta. No se ha falseado la posición de inicio con ninguna altura mínima. Test: `AgendaTimelineGridTest` («a short appointment block shows a single truncated line, not a broken second line»).

### N4. La rejilla tenía su propio *scroll*, anidado en el de la página

- **Estado:** resuelto.
- **Resolución:** se quitó `overflow-y-auto; max-height: 70vh` de `#timeline-scroll` (Día) y `#week-timeline-scroll` (Semana escritorio); ambas rejillas ocupan ahora su alto natural. El script de auto-desplazamiento usa `container.getBoundingClientRect().top + window.scrollY + nowLineTop - 100` y `window.scrollTo()` en vez de `container.scrollTop`, moviendo la página en lugar del contenedor. La cabecera de días de Semana (escritorio) es `sticky top-0 z-20 bg-black`, como sugería el hallazgo. Test: `AgendaTimelineGridTest` («today shows the "ahora" line and the scroll script, at the right offset»), actualizado para comprobar el nuevo script.

### N5. La etiqueta de las 09:00 salía cortada, con poco contraste

- **Estado:** resuelto.
- **Resolución:** quitado el `<span class="relative top-[-5px]">` de `_timeline-hour-axis.blade.php` (la causa del recorte: desplazaba la primera etiqueta por encima del borde superior del contenedor, que no tiene nada por encima que absorba el desbordamiento); la etiqueta ahora vive en el flujo normal de su propia fila, a `text-xs` (antes `text-[10px]`) y `text-gray-300` (antes `text-gray-500`). Sin test automático de contraste real (hay que comprobarlo visualmente); a confirmar en el navegador.

### N6. No se veía qué carril era cada plaza

- **Estado:** resuelto.
- **Resolución:** Día (`_timeline.blade.php`) muestra una cabecera discreta `"Plaza 1 · Plaza 2"` (una celda por carril, `aria-hidden="true"` porque cada `aria-label` ya lo dice) encima de la rejilla. En Semana, las columnas son demasiado estrechas para una cabecera por columna; se dejó solo en el `aria-label` de cada cita/hueco/cierre-parcial, tal como permitía el propio hallazgo. Test: `AgendaCalendarAccessibilityTest` («each hueco libre in the Día timeline says its "Plaza N"»), que comprueba tanto la cabecera visible como el `aria-label`.

---

## Para comprobar en el navegador (lo hace el coordinador)

Puntos de esta revisión y de N1-N6 que no tienen test automático (visuales, o de navegación con teclado/lector de pantalla), más los que T036–T040 ya habían dejado pendientes a mano (no repetidos en otro sitio):

1. **375 px, vista Día, 2 carriles:** confirmar que la hora, el nombre de la clienta y (si cabe) el servicio se leen bien dentro de cada carril de ~140-165 px, con el recorte `ellipsis` actuando cuando el nombre es largo, y que un bloque corto (15 min) ya no corta su texto a medias (N3).
2. **1024 px (o el ancho de escritorio real), vista Semana, 14 carriles (7 columnas × 2):** confirmar que el texto no rompe la maquetación, que la hora + nombre siguen siendo legibles en columnas de ~70-140 px de ancho por carril, y que la cabecera de días se queda fija (`sticky`) al desplazarse (N4).
3. **Líneas de hora/media hora (N2):** confirmar que se ven en todo el ancho de los carriles, con la de la hora más marcada que la de la media hora, alineadas con el eje de horas de la izquierda y sin saltos entre un tramo y el siguiente.
4. **Etiquetas del eje de horas (N5):** confirmar que la de las 09:00 ya no sale cortada por arriba y que se lee bien (tamaño y contraste) en el tema oscuro.
5. **Foco visible:** tabular por varios huecos libres y bloques de cita (Día y Semana) y comprobar que el anillo de foco del navegador se distingue con buen contraste sobre el fondo oscuro (`#1c1c1c`/negro) — no se ha tocado ningún `outline`/`focus:` en este diff, pero conviene confirmarlo visualmente.
6. **Lector de pantalla (VoiceOver/NVDA), Día y Semana:** navegar con Tab y con el lector para juzgar si el nuevo orden cronológico global (L4) y el `aria-label` de cada "Plaza N" (sin el `role="group"` de antes) se entienden sin mirar la pantalla, y si en Semana la fecha ya presente en los `aria-label` (M2) ayuda a saber qué columna/día se está escuchando. Confirmar también que el enlace «Saltar a las citas» (L4) funciona y lleva a la lista de tarjetas.
7. **Auto-scroll a «ahora», sin doble *scroll* (N4):** abrir la agenda en el día de hoy (Día y Semana) y comprobar que la página (no un contenedor interno) ya está desplazada a la hora actual sin tocar nada, que al abrir un día que no es hoy no hay ningún salto, y que ya no hay dos barras de *scroll* anidadas.
8. **Tocar un hueco libre (N1):** en móvil (Día) y en una columna de Semana en escritorio que no sea el día seleccionado por la tira, confirmar que cada media hora libre es su propio enlace de 44 px (no todo el tramo libre un solo enlace) y que «Nueva cita» abre con la fecha, la hora y `volver` correctos — en particular que un hueco de una columna de Semana crea la cita en el día de **esa columna**, no en el de la tira móvil.
9. **Bandas y «Sobre capacidad»:** revisar visualmente que «Cerrado»/«Fuera de horario»/«Cierre» (patrón de rayas + texto) y el borde ámbar + texto «Sobre capacidad» de una cita se distinguen sin depender solo del color, también con el contraste real del tema oscuro; de paso, forzar una cita fuera de hora («Guardar igualmente», H1) o en un día cerrado (M1) y comprobar que ahora se ve en su carril.
