# Revisión independiente: filtro de servicio en la agenda (T042–T045)

- **Alcance:** `git diff feature/agenda-timeline-grid...feature/agenda-service-filter` (4 commits: `e43a20f` T042, `3dc689e` T043+T044, `fc06189` T045, `f2e2ae3` documentación), contrastado con `.ai/specs/reservas.md` (PRF-120 a PRF-124, CA-15) y `.ai/tasks/reservas/T042`–`T045`.
- **Skill:** `review` (`.claude/skills/review/SKILL.md`).
- **Fecha:** 2026-10-05.
- **Revisor:** Claude Sonnet 5, en un contexto limpio. No ha participado en la implementación.
- **Tests:** `docker compose exec -T -u www-data app php artisan test --compact` → reproducido: **536 pasados (2085 aserciones)**. Único aviso: Pest no puede escribir su caché de resultados (permisos del volumen), sin efecto en el resultado — igual que en revisiones anteriores de este proyecto.
- **Build:** `docker compose exec -T node npm run build` → reproducido: compila sin errores (solo el aviso informativo de tiempos del plugin de Tailwind). El contenedor `node` no se ha reiniciado.
- **Pint:** `docker compose exec -T -u www-data app vendor/bin/pint --test --format agent` → **2 archivos de este diff con hallazgos de estilo** (L1, abajo), aunque T042–T045 declaran Pint en su verificación.
- **Navegador:** no lo he comprobado yo (sin sesión de usuario en este contexto). Ver «Para comprobar en el navegador» al final.
- **Resolución (2026-10-05):** M1, L1 y los tres puntos N1-N3 del coordinador (aprobados por el usuario) resueltos en la misma rama, commits `3ba0249` (M1, L1, N1, N2) y `f711573` (N3, en su propio commit). Detalle en cada hallazgo y en la sección «Puntos del coordinador» más abajo. Suite completa tras la resolución: **543 pasados (2104 aserciones)**.

## Resumen

| Severidad | Nº |
| --- | --- |
| Critical | 0 |
| High | 0 |
| Medium | 1 |
| Low | 1 |

- M1. El formulario del selector de servicio (`<select>` + «Ver») no tiene ningún límite de ancho ni `flex-wrap`/`min-w-0`: con un nombre de servicio real algo largo, puede desbordar la fila a 360 px en vez de ajustarse, igual que ya pasó con otras filas de controles de esta misma agenda en revisiones anteriores (N2 de `agenda-calendar-views`).
- L1. `tests/Feature/Admin/AgendaServiceFilterTest.php` y `tests/Feature/Booking/AvailabilityFitForServiceTest.php` (ambos nuevos en este diff) no pasan `vendor/bin/pint --test`, pese a que T042–T045 declaran Pint en su «Verificación».

## Comprobaciones sin hallazgos

- **`fittingStartMinutes()` y la refactorización de `rangeContaining()`.** `AvailabilityCalculator::isAvailable()`/`unavailabilityReason()` llaman ahora a `rangeContaining()` (`app/Booking/AvailabilityCalculator.php:241-247`) con la misma expresión exacta que antes tenían inline (`$startMinute >= $range->opensAtMinutes() && $startMinute + $durationMinutes <= $range->closesAtMinutes()`); no cambia ningún operador ni límite. `availableStartTimes()` no se ha tocado (sigue recorriendo los tramos con su propio bucle, cumpliendo la regla 1 por construcción, como documenta T042). Comprobado leyendo el diff línea por línea, no solo el resumen del informe.
- **Equivalencia con `isAvailable(..., applyPublicRules: false)`.** `AvailabilityFitForServiceTest.php` › «it always agrees with isAvailable for the panel» ejecuta 25 semillas × 4 duraciones (100 comparaciones) con capacidad aleatoria 1-3, horario partido aleatorio, hasta 6 citas (algunas canceladas) y hasta 2 cierres; reproducido en la ejecución completa de la suite. Los casos puntuales (cierre parcial a mitad del servicio, cierre total, pausa de mediodía, día cerrado, citas canceladas, cita que termina justo cuando empieza la candidata, candidatas fuera de la media hora exacta como `09:05`/`09:10`, los dos domingos de cambio de hora) tienen además un test dedicado cada uno.
- **Cero consultas.** `AvailabilityFitForServiceTest.php` › «it runs no query» usa `DB::enableQueryLog()` alrededor de la llamada (tras cargar el contexto, fuera del log) y espera `[]`; reproducido. `fittingStartMinutes()` en efecto no contiene ninguna sentencia `::query()`, solo trabaja sobre las `Collection` recibidas.
- **«¿Cabe» puede ser falso por capacidad global, no por carril.** `capacityProblem()`/`hasCapacity()` (reutilizadas sin cambios) cuentan todas las citas confirmadas del contexto, sin distinguir carril; el test «a service that would exceed capacity does not fit, regardless of lane» (`AgendaServiceFilterTest.php:71-87`) lo prueba con una cita real que no se solapa con la propia media hora candidata pero sí con el resto de la duración del servicio: el hueco se marca correctamente como no disponible en el único carril existente. Coincide con el razonamiento esperado: los carriles son solo de maquetación (`AppointmentLaneAssigner`), nunca entran en el cálculo de disponibilidad.
- **Medias horas pasadas y días cerrados.** `DayTimeline::tappableFreeMinutes()` solo lee segmentos ya marcados `tappable` por el código existente (no tocado en este diff), que ya exige `$segment['start'] >= $nowMinute`; un día sin ningún tramo abierto no genera ninguna pieza `'open'`, así que `tappableFreeMinutes()` devuelve `[]` y no hay nada que resaltar. No hace falta un test nuevo porque la garantía es estructural (ningún segmento candidato existe), pero conviene confirmarlo a ojo (ver «Para comprobar en el navegador»).
- **Rango ampliado (H1 de `agenda-timeline-grid`).** Una cita forzada fuera del horario normal («Guardar igualmente») solo sobrevive en su propio carril dentro del sub-tramo exacto que ocupa (`closedGap()`/`splitByClosure()`, sin cambios en este diff); ese sub-tramo no deja ningún hueco libre antes o después en ese mismo carril (el cursor de `laneSegments()` arranca y termina justo en los límites de la cita), así que no hay ningún candidato «Cabe» espurio dentro de una zona conceptualmente cerrada.
- **Validación de `servicio`.** `AgendaController::servicioFromQuery()` (`app/Http/Controllers/Admin/AgendaController.php:82-89`) exige `is_numeric()` antes de comparar contra la lista ya cargada de servicios **activos** (en memoria, sin segunda consulta): un valor vacío, no numérico, un id inexistente o el de un servicio inactivo se ignoran en silencio, nunca un error — probado explícitamente (`AgendaServiceFilterTest.php`, casos `''`, `'no-es-un-id'`, `'999999'`, y un servicio inactivo real). Al no construirse ninguna consulta SQL con el valor crudo (la comparación es en PHP, sobre una colección ya cargada con `where('is_active', true)`), una cadena de inyección no tiene ningún punto de entrada; `is_numeric()` además la descarta de entrada.
- **Servicios no reservables online en el panel.** El selector carga `Service::where('is_active', true)->ordered()->get()`, igual que ya hacía `AppointmentController::create()` antes de este diff: un servicio activo pero no reservable online sí aparece (solo PRF-016 lo excluye de la página pública y de los servicios inactivos), consistente con el criterio ya existente para crear citas desde el panel.
- **Persistencia de `servicio`.** `$servicioQuery` se reparte con `...$servicioQuery` en cada enlace de `index.blade.php` (pestañas, Anterior/Siguiente/Hoy, «Hoy», «Nueva cita» de escritorio y el botón flotante), en `_week.blade.php` (tira móvil) y en `_month.blade.php` (celdas de día, con `?? []` de respaldo); el formulario «Ir a la fecha» lleva un campo oculto `servicio` y el propio formulario del selector lleva campos ocultos `fecha`/`vista`, así que ninguno de los dos se pisa. `volver` sigue construyéndose exactamente igual (`"$vista:{$day->toDateString()}"`) y `servicio` nunca entra en ese string — probado (`AgendaServiceFilterTest.php` › «"servicio" and "volver" stay separate query parameters, never combined», y la persistencia a través de pestañas/navegación/Mes/«Nueva cita»).
- **Rendimiento.** `AgendaServiceFilterTest.php` › los dos tests de recuento de consultas confirman 1 sola consulta a `services` (además de las ya existentes a `appointments`, `schedule_blocks`, `opening_hours`, `booking_settings`), igual en Día que en Semana con 7 días y varias citas — nunca una consulta por hueco ni por día. El coste de `fittingStartMinutes()`/`tappableFreeMinutes()` por día en Semana es solo CPU en memoria (sin consulta), sobre colecciones acotadas al día: aceptable para los volúmenes reales de este salón.
- **Preselección en «Nueva cita».** `AppointmentController::create()` reutiliza `AgendaController::servicioFromQuery()` con la misma lista de servicios activos que ya carga para el `<select>` (sin segunda consulta); `create.blade.php:36` usa `old('service_id', $servicio ?? '')`, así que una redisplay tras un error de validación con otro servicio elegido en el propio formulario sigue ganando — mismo orden de precedencia que ya usa `time`. Probado con un `servicio` válido, uno inválido y uno de un servicio inactivo.
- **CSS sin capa que gane a las utilidades.** No hay ningún `<style>` nuevo ni CSS fuera de `resources/css/app.css` (sin cambios) en todo el diff: el borde/fondo de «Cabe» (`border-2 border-gold bg-gold/15`) y el selector usan solo clases de Tailwind.
- **Contraste del dorado «Cabe».** Calculado (WCAG, luminancia relativa): texto `text-gold` (#C9A84C) sobre el fondo resultante de `bg-gold/15` sobre negro (≈ rgb(30,25,11)) da un contraste ≈ 7,7:1 — por encima del mínimo AA (4,5:1) e incluso del AAA (7:1) para texto normal. El borde usa el mismo dorado, con el mismo margen.
- **Nada transmitido solo con color.** El hueco «Cabe» añade borde + fondo (dos propiedades, no solo un tono distinto) y, en Día, el texto «Cabe»; en Semana, el `aria-label` lo dice siempre aunque no haya texto visible — probado con un recuento exacto (`substr_count($html, 'Cabe')`) que confirma que el texto visible solo aparece en Día/la tira móvil, nunca en las columnas de escritorio de Semana, que sí llevan el `aria-label`.
- **`aria-label` en Semana.** Prefijado con el día (`$datePrefix`, patrón ya resuelto en la revisión anterior, hallazgo M2) y con `, cabe {{ $servicio->name }}` al final cuando aplica; probado con el texto exacto `'martes 8, Hueco libre a las 09:00, plaza 1, cabe Balayage'`.
- **El `onchange` con respaldo sin JS.** El `<select>` se envía solo (`onchange="this.form.submit()"`); el botón «Ver» del mismo formulario es un `submit` normal, funciona igual sin JavaScript. Ningún atributo `required`/`disabled` ni manejador adicional interfiere con la navegación por teclado nativa del `<select>` (flechas + Intro, comportamiento del navegador, no reemplazado por nada a medida).
- **Foco y teclado.** El selector es un `<select>` nativo y los huecos «Cabe» siguen siendo el mismo tipo de `<a href>` que ya existía (sin `tabindex` ni manejador que intercepte el foco); no se ha introducido ningún menú, diálogo ni desplegable a medida que necesite atrapar el foco o devolverlo.
- **Patrón de la lección «misma regla en dos sitios, solo una completa» (candidato 23, `syntheses/lecciones-revisiones.md`).** El resaltado se calcula una sola vez, en el método privado compartido `AgendaController::markServiceFit()`, llamado tanto desde `dayData()` como desde `weekData()` (día a día) — no hay dos implementaciones paralelas que puedan desincronizarse.

---

## Medium

### M1. La fila del selector de servicio no tiene ningún límite de ancho ni se puede partir en dos líneas

- **Estado:** resuelto (commit `3ba0249`).
- **Resolución:** el formulario pasa a `flex flex-wrap` y el `<select>` a `min-w-0 flex-1` (un elemento flex sin `min-width: 0` explícito conserva `min-width: auto`, calculado a partir del contenido de sus `<option>`, que es justo lo que podía desbordar); la etiqueta y «Ver» llevan `shrink-0`. Con `flex-wrap`, si aun así no cabe, «Ver» cae a su propia línea en vez de forzar *scroll* horizontal. Test con un nombre de servicio de 44 caracteres (más largo que cualquier servicio real) comprobando las clases y que el nombre completo sigue en el HTML.
- **Evidencia:** `resources/views/admin/agenda/index.blade.php:109` — `<form method="GET" ... class="flex items-center gap-2 text-sm mb-6">`, sin `flex-wrap` ni `min-w-0`/`max-w-*` en el `<select>` (línea 115, `class="bg-black border border-[#2A2A2A] px-2 py-3"`). Un elemento `<select>` dentro de un contenedor `flex` sin `min-width: 0` explícito conserva su `min-width: auto` por defecto, que en la mayoría de motores se calcula a partir del contenido de sus `<option>` (nombre del servicio + duración, hasta 100 caracteres de nombre más el sufijo «(2 h 30 min)»): si algún servicio real tiene un nombre algo largo, la fila `label` + `select` + botón «Ver» puede desbordar horizontalmente en vez de ajustarse, en un ancho de 360 px.
- **Impacto:** esta misma familia de problema (una fila de controles que no cabe o se parte mal a 360-375 px) ya apareció como hallazgo N2 en la revisión `agenda-calendar-views` y se corrigió entonces con iconos en vez de texto; aquí no hay ningún mecanismo equivalente. Si ocurre, el salón vería *scroll* horizontal o el botón «Ver» cortado/fuera de pantalla justo en el ancho que más usa (PRF-089 a PRF-098 fijan 360-414 px como objetivo explícito de esta agenda).
- **Recomendación:** añadir `min-w-0` al formulario o al `<select>` y, bien `flex-wrap` en el formulario (dejando que el botón baje de línea si no cabe), bien un `max-w-[...]`/`truncate` en el `<select>` para que nunca crezca más que el ancho disponible. Confirmarlo en el navegador a 360 px con los nombres de servicio reales del salón (no solo con «Corte»/«Balayage» de los tests), ver «Para comprobar en el navegador».

## Low

### L1. Dos archivos de test nuevos no pasan Pint, pese a que T042–T045 lo declaran en su verificación

- **Estado:** resuelto (commit `3ba0249`).
- **Resolución:** añadidos los dos `use` que faltaban (`App\Models\BookingSetting` en `AgendaServiceFilterTest.php`, `Illuminate\Support\Collection` en el docblock de `agendaContext()` de `AvailabilityFitForServiceTest.php`) en vez de las referencias totalmente cualificadas. Reproducido `vendor/bin/pint --test -v` sobre los 9 archivos PHP de todo el diff de la rama (no solo `--dirty`): **pasa limpio**.
- **Evidencia:** `docker compose exec -T -u www-data app vendor/bin/pint --test -v` señala:
  - `tests/Feature/Admin/AgendaServiceFilterTest.php:72` — `\App\Models\BookingSetting::current()` usa el nombre totalmente cualificado en vez de importarlo (`fully_qualified_strict_types`); falta `use App\Models\BookingSetting;`.
  - `tests/Feature/Booking/AvailabilityFitForServiceTest.php:58` — el docblock de `agendaContext()` usa `Illuminate\Support\Collection` sin importar la clase; falta `use Illuminate\Support\Collection;`.
- **Impacto:** puramente de estilo (ninguno de los dos cambia comportamiento), pero contradice la propia «Verificación» de T042–T045, que cita `vendor/bin/pint --dirty --format agent` como paso hecho antes de marcar las tareas `done` — mismo patrón A («hecho sin evidencia») que recoge `syntheses/lecciones-revisiones.md`.
- **Recomendación:** ejecutar `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` (lo aplica automáticamente) y volver a correr la suite.

---

## Puntos del coordinador en Chrome (N1-N3, 2026-10-05)

Vistos por el coordinador (N1, N2) o investigados a su petición (N3); **aprobados por el usuario** y resueltos en `3ba0249` (N1, N2) y `f711573` (N3, en su propio commit).

### N1. «Cabe» se marcaba en una plaza aunque su propia cita la pisara

- **Estado:** resuelto.
- **Resolución:** `DayTimeline::markServiceFit()` recibe ahora también `$durationMinutes` y, para cada minuto candidato, solo resalta los carriles sin ninguna cita en `[minuto, minuto + duración)` — no todos los que «caben» por capacidad. Si ningún carril queda libre todo ese tiempo (citas escalonadas en carriles distintos, sin coincidir nunca lo bastante para romper la capacidad), resalta el primer carril libre en esa media hora, el mismo que el propio algoritmo de primer-carril-libre asignaría a una cita nueva — no hay ningún carril «más seguro» al que señalar. No toca `AvailabilityCalculator`: es presentación, en `DayTimeline`. Tests: el caso real (Josep a las 11:40, plaza 1 no / plaza 2 sí a las 11:00) a nivel HTTP (`AgendaServiceFilterTest`), y tres a nivel de `DayTimeline` con `fittingMinutes` dado a mano para aislar la lógica de carriles de la de capacidad (`DayTimelineServiceFitTest`): el caso escalonado (ningún carril continuo → se marca el primero), capacidad 1 (el único carril se marca igual, aunque no esté libre todo el rato) y un minuto que no cabe por capacidad (nunca se marca, en ningún carril).

### N2. El borde dorado completo dejaba toda la rejilla dorada con un servicio corto

- **Estado:** resuelto.
- **Resolución:** sustituido `border-2 border-gold bg-gold/15` por una franja a la izquierda y un fondo más tenue, `border-l-4 border-gold bg-gold/10` — sigue siendo borde + fondo, nunca solo un cambio de color — con el texto «Cabe» solo en Día y el `aria-label` siempre. Test que comprueba las clases nuevas y la ausencia de las antiguas.

### N3. La primera compilación de Vite en desarrollo tardaba 91 s (y empeorando)

- **Estado:** resuelto, en un commit aparte (`f711573`).
- **Causa:** `resources/css/app.css` dejaba activa la detección automática de fuentes de Tailwind 4 (`@import 'tailwindcss';` sin `source(none)`), que recorre toda la raíz del proyecto — no solo `resources/` — buscando nombres de clase: `.ai/`, `.claude/`, `app/`, `tests/`, `database/`, `docker/`, `storage/`, incluso `.git/`, todo en el *bind mount* lento de Windows (`vendor/`/`node_modules/` se libran por estar en volúmenes nombrados, pero nada más). Generaba además CSS de clases que ninguna vista ni JS usa — casi con toda seguridad texto suelto en la abundante documentación Markdown de este repo que por casualidad parece una clase de Tailwind.
- **Resolución:** `@import 'tailwindcss' source(none);` más los `@source` explícitos que ya existían (cubren ya todo Blade/JS); `vite.config.js` amplía `server.watch.ignored` de solo `storage/framework/views/**` a también `vendor/`, `node_modules/`, el resto de `storage/`, `.git/` y `public/build/`, para que el *polling* de 300 ms deje de recorrerlos en cada ciclo.
- **Medición:** `curl -s -o /dev/null -w "%{time_total}"` contra `http://127.0.0.1:5175/resources/css/app.css` justo tras un `docker compose restart node`: **107,7 s antes, 0,77 s después**; `npm run build` pasó de ~1 min 30 s a menos de 1 s.
- **Comprobación de clases:** diff de los selectores del CSS compilado (`npm run build`) antes y después (`grep -rF` de cada selector «perdido» sobre `resources/`): ninguno se usa en ningún `.blade.php`/`.js` real — son o bien clases que este mismo informe deja de usar a propósito (N2, `border-2`/`bg-gold/15`) o bien las detecciones espurias que motivaron el hallazgo. Ninguna clase real de las vistas se ha perdido.
- Documentado en `AGENTS.md`, «Known traps».

---

## Para comprobar en el navegador (lo hace el coordinador)

1. **Selector a 360 px (M1):** con los servicios reales del salón ya cargados en local, abrir Día y Semana a 360 px de ancho y comprobar que la fila «Servicio» + `<select>` + «Ver» no desborda ni corta el botón (si no cabe, «Ver» debe caer a su propia línea), y que tampoco rompe la fila en escritorio (≥1024 px) si algún nombre es largo.
2. **«Cabe» en Día, con datos reales:** elegir un servicio y confirmar visualmente la franja/fondo dorado (ya no un borde completo, N2) y el texto «Cabe» en cada hueco libre donde cabe, y que un hueco donde no cabe se ve exactamente igual que sin ningún servicio elegido.
3. **«Cabe» por carril (N1), con datos reales:** con la cita de Josep del 7/10 (11:40, 15 min) y un servicio de 1 hora elegido, confirmar que a las 11:00 solo se resalta la plaza 2, nunca la plaza 1 (que la cita pisaría).
4. **«Cabe» en Semana (escritorio):** confirmar que ninguna columna muestra el texto «Cabe» (solo la franja/fondo) y, con un lector de pantalla o inspeccionando el DOM, que el `aria-label` de cada hueco que cabe incluye el día de esa columna y el nombre del servicio.
5. **Teclado:** tabular por el `<select>` y comprobar que las flechas + Intro cambian el servicio y recargan la agenda sin perder el foco de forma confusa; tabular por varios huecos «Cabe» y sin «Cabe» y comprobar que el anillo de foco se distingue igual que en el resto de la rejilla (no se ha tocado ningún `outline`/`focus:` en este diff).
6. **Persistencia real:** con un servicio elegido, crear una cita desde un hueco «Cabe», guardarla, y confirmar que la agenda vuelve con el mismo servicio todavía seleccionado (no solo en la URL: también marcado en el `<select>`); repetir cancelando y editando una cita existente con `volver`.
7. **Contraste con el tema real del dispositivo:** confirmar a ojo, en una pantalla real (no solo el cálculo WCAG de este informe), que el dorado de «Cabe» se distingue bien del hueco libre normal con poca luz ambiente, ahora con la franja más sutil (N2).
8. **Vite tras el cambio (N3):** confirmar que `npm run dev` sigue sirviendo CSS/JS con normalidad (hot reload al tocar una vista) y que no ha quedado ningún estilo roto por `source(none)`.
