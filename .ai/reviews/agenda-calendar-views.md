# Revisión independiente: vistas Día/Semana/Mes de la agenda (T029–T032)

- **Alcance:** `git diff feature/mobile-admin-ux...feature/agenda-calendar-views` (6 commits: `060e24d` *polling* de Vite, `d3c9947` T029, `790b4e7` T030, `8b3bc3e` T031, `f38422b` y `41aa7e6` T032), contrastado con `.ai/specs/reservas.md` (PRF-099 a PRF-107, CA-14) y `.ai/tasks/reservas/T029`–`T032`.
- **Skill:** `review` (`.claude/skills/review/SKILL.md`).
- **Fecha:** 2026-10-05.
- **Revisor:** Claude Sonnet 5, en un contexto limpio. No ha participado en la implementación.
- **Tests:** `docker compose exec -T -u www-data app php artisan test --compact` → reproducido: **397 pasados (1543 aserciones)**, igual que registra T032. Único aviso: Pest no puede escribir su caché de resultados (`.temp/test-results`, permisos del volumen), sin efecto en el resultado.
- **Build:** `docker compose exec -T node npm run build` → reproducido: compila sin errores (solo el aviso informativo de tiempos del plugin de Tailwind). El contenedor `node` no se ha reiniciado.
- **Nota sobre el proceso de esta revisión:** al empezar, invoqué por error la *skill* genérica `code-review` del sistema (no la de este proyecto), que se ejecutó en segundo plano de forma independiente, llegó a escribir una primera versión de este mismo archivo y también tocó archivos del Developer Brain (`projects/peluqueriajenver.md`, `syntheses/lecciones-revisiones.md`, `log.md`) siguiendo la regla de actualización continua de `CLAUDE.md`. No pude detener esa tarea (no era de mi propiedad). Sus hallazgos (H1, M1, L1 de más abajo) los he verificado yo mismo de forma independiente, leyendo el código y reproduciendo los tests citados, antes de incorporarlos; el resto de hallazgos y el resto del contenido de este archivo son de esta revisión. Aviso para el coordinador: conviene revisar qué se escribió en el Developer Brain por ese proceso paralelo, ya que no estaba previsto.
- **Navegador:** no lo he comprobado yo (sin sesión de usuario en este contexto). Ver «Para comprobar en el navegador» al final.

## Resumen

| Severidad | Nº | Resueltos | Anotados sin cambio |
| --- | --- | --- | --- |
| Critical | 0 | — | — |
| High | 1 | 1 | 0 |
| Medium | 3 | 2 | 1 |
| Low | 3 | 2 | 1 |

Más 3 hallazgos del coordinador en el navegador (N1-N3), los 3 resueltos — ver «Hallazgos del coordinador en el navegador» más abajo.

- H1. ~~La vista Semana no marca como «Cerrado» un día cubierto por un cierre total (`ScheduleBlock`), al contrario que la vista Mes y que exige PRF-105.~~ **Resuelto.**
- M1. ~~Crear, mover o cancelar una cita desde la vista Semana o Mes redirige siempre a la vista Día, perdiendo el contexto de navegación que PRF-099 acaba de introducir.~~ **Resuelto.**
- M2. Las celdas de la rejilla de Mes no alcanzan los 44 px de ancho en móviles de 360 px (dos niveles de relleno anidados). **Aceptado por el usuario (39-43 px), sin cambio.**
- M3. ~~Las rejillas de Semana (escritorio) y Mes declaran `role="grid"`/`role="columnheader"` sin `role="row"` ni `role="gridcell"`: una semántica de rejilla incompleta, heredada del calendario público.~~ **Resuelto, incluido el calendario público.**
- L1. ~~`$weekEnd` tiene dos significados distintos entre `index()` y `weekData()`, sin que el nombre lo distinga.~~ **Resuelto.**
- L2. La tira de 7 días de Semana (móvil) queda por debajo de 44 px de ancho a 360 px, por un margen pequeño (~43,4 px calculados). **Aceptado por el usuario, sin cambio (junto con M2).**
- L3. ~~Cuando la semana mostrada cruza de un mes a otro, la rejilla de escritorio no indica el cambio de mes en las cabeceras de día.~~ **Resuelto.**

## Comprobaciones sin hallazgos

- **PRF-099 (selector y `?vista=invalido`).** `AgendaController::viewFromQuery()` (`app/Http/Controllers/Admin/AgendaController.php:46-49`) solo acepta `dia`/`semana`/`mes` con comparación estricta (`in_array(..., true)`) tras comprobar `is_string`; cualquier otro valor, incluido un array (`?vista[]=x`), cae a `dia` sin error. Reproducido: `an invalid vista falls back to día` pasa.
- **`fecha` (validación, ataques).** `dayFromQuery()` no ha cambiado en este diff (ya existía). Sigue validando con una expresión regular estricta más un `format()` de ida y vuelta que descarta fechas imposibles (`2026-13-01`, `2026-01-32`) cayendo a hoy; no hay límite explícito de año muy lejano, pero como las consultas de Semana y Mes siempre acotan el rango a 7 días o a un mes natural, una fecha absurda o muy lejana no da lugar a una consulta sin acotar ni a un bucle largo — solo a una vista vacía. No es una regresión de este diff.
- **Semana lunes-domingo, cambios de hora, bisiestos, cruces de mes/año.** Comprobado en vivo con Tinker (sin escribir en base de datos): `$day->startOfWeek(CarbonInterface::MONDAY)` da siempre lunes aunque el *locale* sea `es`; una semana que incluye el cambio de hora de marzo de 2026 (noche del 28 al 29) o de octubre de 2026 (noche del 24 al 25) sigue teniendo 7 días a medianoche sin solapes ni huecos; `daysInMonth` de febrero de 2028 (bisiesto) da 29 y el mes siguiente empieza el 1 de marzo; una semana que cruza de 2030 a 2031 da `2030-12-30` → `2031-01-06`. Todo correcto porque la aritmética de `AgendaController` opera siempre a nivel de día civil (medianoche), nunca con duraciones en segundos. Esto no está cubierto por un test automático (ver L3 más abajo, relacionado pero distinto).
- **`DATE(starts_at)` en MySQL y SQLite, con zona horaria.** `starts_at` es `dateTime` (no `timestamp`) y la conexión `mysql` no fija `'timezone'` en `config/database.php`; con `config('app.timezone') = 'Europe/Madrid'`, Eloquent guarda y lee la hora local sin convertir a UTC, igual en SQLite. `DATE(starts_at)` es una función estándar tanto en MySQL como en SQLite (`date()`, insensible a mayúsculas) que actúa sobre la misma cadena `'Y-m-d H:i:s'` en los dos motores, así que `monthData()` (`AgendaController.php:131-136`) da el mismo resultado en ambos sin conversión de zona horaria de por medio.
- **N+1.** `dayData()`, `weekData()` y `monthData()` hacen como mucho 2 consultas cada una (citas/ocupación + cierres), reproducido con los tests que usan `DB::enableQueryLog()` (`AgendaWeekViewTest.php:92-109`, `AgendaMonthViewTest.php:93-107`). Ninguna vista de Blade dispara consultas adicionales: `service_name`, `customer_phone`, etc. son columnas propias de `Appointment` (no relaciones), y `openWeekdays()` se llama una sola vez por petición (nunca dentro de un bucle por día).
- **Cierres mostrados en Mes: solo los totales.** `monthData()` filtra `whereNull('capacity_reduction')` (`AgendaController.php:138-140`); un cierre con reducción parcial de capacidad no marca el día como cerrado (`tests/Feature/Admin/AgendaMonthViewTest.php:60-66`, reproducido) y no tiene ningún otro indicador visual en Mes — PRF-105 solo exige marcar los cierres totales, así que esto no es un hallazgo, pero conviene leerlo junto con H1: Semana ni siquiera hace esta parte.
- **Recuento de citas canceladas.** La tira de Semana (`$d['appointments']->filter->isConfirmed()->count()`) y el agregado de Mes (`Appointment::query()->confirmed()`) excluyen ambos las citas canceladas del número mostrado; la cita cancelada se sigue viendo, atenuada (`line-through`/`opacity-60`), en la agenda del día. Reproducido con `the week view day strip only counts confirmed appointments, not cancelled ones`.
- **Extracción de `_day.blade.php`.** El contenido es un recorte literal de lo que antes vivía en `index.blade.php` (diferido contra el `index.blade.php` previo a este diff): mismo marcado, mismos `aria-label`, mismo formulario de cancelación, mismo botón flotante «+» y menú hamburguesa (ninguno de los dos ha cambiado de archivo). Sin regresión para la vista Día.
- **Navegación Anterior/Siguiente/Hoy.** El bloque de `index.blade.php` reutiliza la misma clase `btn-outline w-11 px-0` ya medida en Día para Semana y Mes (`semana and mes navigation buttons reuse the 44px Día button style`, reproducido); «Mañana» solo aparece en Día; «Hoy» de Semana/Mes no lleva `fecha`, así que cae correctamente en la semana/mes que contiene el día de hoy.
- **`vite.config.js`.** El cambio está dentro de `server.watch` (solo afecta al servidor de desarrollo de Vite); `vite build` no lee esa sección. Confirmado con el build reproducido arriba: compila igual que antes.

---

## High

### H1. La vista Semana no marca como «Cerrado» un día cubierto por un cierre total (`ScheduleBlock`)

- **Estado:** resolved
- **Resolución:** `weekData()` (`app/Http/Controllers/Admin/AgendaController.php`) ahora calcula `$dayBlocks` por día (ya se cargaban, pero solo se usaban para el día seleccionado) y marca `isClosed` también cuando hay un cierre total (`capacity_reduction === null`) solapando ese día, igual que `monthData()`. Además, un cierre de capacidad reducida (parcial) que no cierra el día se señala aparte con `hasPartialClosure`, tal y como pide el encargo («igual que Día»). En `_week.blade.php`: la tira móvil añade «cerrado»/«menos plazas» al `aria-label` y un texto visible en la pastilla; la rejilla de escritorio muestra «Cerrado»/«Capacidad reducida» **y sigue listando las citas del día** (antes de esta resolución no se mostraba ninguna cita en un día cerrado; ahora el cierre y las citas supervivientes conviven, resolviendo también el caso que el hallazgo señalaba de una cita confirmada antes del cierre). Tests: `tests/Feature/Admin/AgendaWeekViewTest.php` › «the week view marks a day covered by a full closure as closed, even with a surviving appointment» y «the week view flags a day with a partial closure, without marking it closed».
- **Impacto:** PRF-105 exige literalmente: «Un día sin horario semanal (PRF-017) **o** cubierto por un cierre total (PRF-021) debe marcarse como "Cerrado" con texto, no solo con color, **en las vistas Semana y Mes**». Si el salón declara unas vacaciones o cierra un día concreto dentro de la semana mostrada, la vista Semana lo sigue mostrando como si estuviera abierto:
  - En la tira móvil, la pastilla de ese día no se atenúa ni su `aria-label` dice «cerrado»; su recuento sigue leyendo «sin citas» o «N citas» como cualquier día normal.
  - En la rejilla de escritorio, el día muestra «Sin citas» (si no hay citas) en vez de «Cerrado» — dando a entender que el hueco sigue disponible cuando el salón ha cerrado ese día.
  - Si además hay una cita confirmada antes del cierre (PRF-022 permite que un cierre nuevo conviva con citas ya confirmadas, sin cancelarlas), la rejilla de escritorio solo lista esa cita, sin ningún indicio de que el resto del día está cerrado.
  - En móvil, un cierre total en un día de la semana **distinto** del seleccionado no se ve en ningún sitio: el aviso ámbar «Cierre total: …» de `_day.blade.php` solo se incluye para el día elegido (`_week.blade.php:40`), así que no hay ninguna pista visual para el resto de días de la tira.
  La vista Mes, para la misma fecha, lo marca correctamente.
- **Evidencia:**
  - `app/Http/Controllers/Admin/AgendaController.php:113`, dentro de `weekData()`:
    ```php
    'isClosed' => ! in_array($date->isoWeekday(), $openWeekdays, true),
    ```
    Solo comprueba `openWeekdays()` (horario semanal recurrente); nunca consulta `ScheduleBlock`, a diferencia de `monthData()` (`AgendaController.php:138-141`):
    ```php
    $fullClosures = ScheduleBlock::query()->whereNull('capacity_reduction')->overlapping($month, $monthEnd)->get(['starts_at', 'ends_at']);
    ```
    y de su uso en `resources/views/admin/agenda/_month.blade.php:24-25`:
    ```php
    $isClosed = ! in_array($date->isoWeekday(), $openWeekdays, true)
        || $fullClosures->contains(fn ($block) => $block->starts_at->lt($date->addDay()) && $block->ends_at->gt($date));
    ```
  - Ese `isClosed` de `weekData()` se usa tal cual en la pastilla móvil (`resources/views/admin/agenda/_week.blade.php:21,23`), en el aviso bajo la tira (`_week.blade.php:36`, `@if ($selected['isClosed'])`) y en la rejilla de escritorio (`_week.blade.php:54`, `@if ($d['isClosed']) ... @else ... Sin citas ...`).
  - `weekData()` sí carga `$blocks` (línea 101) y los reparte por día (línea 112), pero solo se usan para pasárselos al `_day` incluido del día seleccionado (`_week.blade.php:40`), nunca para calcular `isClosed`, y nunca se muestran en la rejilla de escritorio (que no incluye `$d['blocks']` en absoluto).
  - `tests/Feature/Admin/AgendaMonthViewTest.php:46-54` tiene un test dedicado («the month view marks weekdays without opening hours and full closures as closed») que crea un `ScheduleBlock` con `capacity_reduction: null` y comprueba el `aria-label` «cerrado». `tests/Feature/Admin/AgendaWeekViewTest.php` no tiene ningún test equivalente: el único `ScheduleBlock` que crea (línea 98, en «the week view loads its appointments and blocks with one query each») solo comprueba el número de consultas, no si el día aparece marcado como cerrado; y «the week view marks days without opening hours as closed» (líneas 57-62) solo usa días sin `opening_hours`, nunca un cierre puntual.
- **Recomendación:** en `weekData()`, calcular `$fullClosures` igual que ya hace `monthData()` (`ScheduleBlock::query()->whereNull('capacity_reduction')->overlapping($weekStart, $weekEnd)->get(...)`, una consulta más, no una por día) y usarla también en el `isClosed` de cada día: `'isClosed' => ! in_array(...) || $fullClosures->contains(...)`. De paso, mostrar el aviso de cierre en la rejilla de escritorio (hoy no muestra `$d['blocks']` en absoluto). Añadir un test en `AgendaWeekViewTest.php` que reproduzca el de Mes (un `ScheduleBlock` de cierre total dentro de la semana mostrada, comprobando el `aria-label` «cerrado» de la pastilla y el texto «Cerrado» de la rejilla de escritorio) antes de dar PRF-105 por cubierto en Semana.

## Medium

### M1. Crear, mover o cancelar una cita desde la vista Semana o Mes redirige siempre a la vista Día

- **Estado:** resolved
- **Resolución:** decisión del usuario, 2026-10-05: sí merece la pena. Nuevo parámetro `volver` (formato `"vista:fecha"`, por ejemplo `"semana:2026-10-06"`), validado por `AgendaController::volverFromQuery()` contra la misma lista blanca que `vista` y `fecha` (`in_array` estricto + la misma expresión regular y comprobación de ida y vuelta de `dayFromQuery`); si no es válido, devuelve `null` y el llamador cae al comportamiento anterior (`['fecha' => ...]`). El resultado **nunca** se usa como URL cruda: siempre se pasa como *array* de parámetros a `route('admin.agenda', ...)`, así que no puede convertirse en una redirección abierta pase lo que pase en el valor de entrada.
  - La agenda (`index.blade.php`, `_day.blade.php`, `_week.blade.php`) calcula `$volver` una vez (`"$vista:{$day->toDateString()}"`) y lo añade a los enlaces «Nueva cita» (arriba y flotante), «Editar» y a un campo oculto del formulario «Cancelar cita».
  - `AppointmentController::create()`/`edit()` leen `volver` de la query y lo pasan, ya saneado, a la vista como campo oculto (`create.blade.php`, `edit.blade.php`); `store()`/`update()`/`cancel()`/`notMovableResponse()` lo leen de `$request->input('volver')` (cubre query y body) y lo usan para el `redirect()->route('admin.agenda', ...)`.
  - Tests: `tests/Feature/Admin/AdminAppointmentTest.php` (4 tests: campo oculto con valor válido/inválido, `store` y `cancel` vuelven a `vista`/`fecha`) y `tests/Feature/Admin/AdminRescheduleAppointmentTest.php` (4 tests: campo oculto en `edit`, `update` vuelve a `vista`/`fecha`, un valor manipulado cae al comportamiento anterior sin redirección abierta, y `notMovableResponse` también respeta `volver`).
- **Impacto:** las tres acciones de cita del panel —crear (`AppointmentController::store`), mover (`AppointmentController::update`) y cancelar (`AppointmentController::cancel`)— redirigen con `route('admin.agenda', ['fecha' => ...])`, sin `vista`. Antes de este cambio eso era irrelevante porque solo existía Día; ahora que PRF-099 introduce un estado de navegación compartible por URL, alguien que cancele o mueva una cita mientras mira Semana o Mes aterriza en Día sin aviso, perdiendo la vista en la que estaba trabajando. No es una regresión de compatibilidad (los enlaces siguen funcionando) pero sí un hueco frente al objetivo explícito de PRF-099.
- **Evidencia:** `app/Http/Controllers/Admin/AppointmentController.php:53-55` (`store`), `:107` (`update`) y `:130-132` (`cancel`); ninguno de los tres ha cambiado en este diff ni fue tocado por T029–T032, que se limitaron a `AgendaController` y a las vistas de `admin/agenda/`.
- **Recomendación:** decidir con el usuario si merece la pena (el panel se usa sobre todo desde el móvil, donde la vista por defecto suele ser Día) y, si es así, conservar `vista` en esos tres redirects cuando la petición que originó la acción la llevaba — por ejemplo, leyéndola de un campo oculto del formulario de cancelación/edición o de la query de la petición entrante, igual que ya hace el formulario «Ir a la fecha» en `index.blade.php`.

### M2. Las celdas de la rejilla de Mes no alcanzan los 44 px de ancho en móviles de 360 px

- **Estado:** anotado, sin cambio de código. Decisión del usuario, 2026-10-05: acepta que las celdas midan entre 39 y 43 px de ancho a 360 px, manteniendo los 44 px de alto (que sí cumplen). Anotado también en `.ai/specs/reservas.md`, PRF-105.
- **Impacto:** cada celda de día en Mes es un `<a>` dentro de `grid grid-cols-7 gap-1`, sin ancho mínimo propio (`min-h-11` solo fija la altura). Esa rejilla está envuelta en `<div class="border border-[#2A2A2A] p-4 sm:p-6">`, que a su vez vive dentro de `<main class="max-w-6xl mx-auto px-4 py-8">` del layout del panel. A 360 px de ancho de viewport (el umbral que ya usa PRF-096 para el horario semanal): `360 − 32 (px-4 del main) − 34 (border + p-4 del contenedor de Mes) − 24 (6 huecos de `gap-1`) = 270 px`, repartidos entre 7 columnas ≈ **38,6 px por celda**, un 12 % por debajo del mínimo de 44 px que el proyecto exige de forma consistente para los elementos interactivos (PRF-089 y, en el mismo módulo, PRF-101 para la tira de Semana). A partir de ≈414 px de ancho el cálculo ya supera los 44 px, así que el problema es específico de los móviles más estrechos (360-390 px, un rango real: Galaxy S8/S20, Pixel 4a, etc.).
- **Evidencia:** `resources/views/admin/agenda/_month.blade.php:10` (`border border-[#2A2A2A] p-4 sm:p-6`), `:11` (`grid grid-cols-7 gap-1`), `:30` (clase de la celda, solo `min-h-11`); `resources/views/layouts/admin.blade.php:150` (`max-w-6xl mx-auto px-4`). Cálculo hecho a mano a partir de las clases de Tailwind (espaciado por defecto: `px-4`/`p-4` = 16 px, `gap-1` = 4 px); no he podido medirlo en un navegador real en este contexto (ver «Para comprobar en el navegador»).
- **Recomendación:** pedir al coordinador que mida con el inspector, a 360 px, el ancho real de una celda de día en Mes. Si se confirma por debajo de 44 px, reducir el relleno horizontal del contenedor en móvil (por ejemplo `p-2 sm:p-6` en vez de `p-4 sm:p-6`, o quitar el `border`/`padding` propio del contenedor en el breakpoint más estrecho) hasta que las 7 columnas quepan con al menos 44 px cada una.

### M3. Las rejillas de Semana (escritorio) y Mes declaran `role="grid"`/`role="columnheader"` sin `role="row"` ni `role="gridcell"`

- **Estado:** resolved
- **Resolución:** completada la semántica ARIA (no se renunció al rol `grid`) en las tres rejillas que la usan, no solo en las dos de este diff:
  - `_month.blade.php` y `pages/partials/reservas-calendar.blade.php`: los días del mes se agrupan ahora en semanas (`array_chunk`) y cada semana se envuelve en `<div role="row" class="contents">`; cada día es `role="gridcell"` (el `<a>` disponible o el `<span>` no disponible, en el calendario público); los huecos iniciales pasan de `aria-hidden="true"` suelto a `role="presentation" aria-hidden="true"`. `class="contents"` (la utilidad `display: contents` de Tailwind) hace que el `<div role="row">` no participe en el `grid-cols-7` del contenedor — sus hijos pasan a ser los elementos de rejilla directos, como si el `<div>` no existiera visualmente — así que la disposición en pantalla no cambia, solo la agrupación semántica.
  - `_week.blade.php` (rejilla de escritorio): se separó en dos filas ARIA (`role="row"`) en vez de una sola fila con la cabecera incrustada en cada celda: una fila de cabeceras (`role="columnheader"`, con el nombre del día) y una fila de contenido (`role="gridcell"`, con «Cerrado»/citas), con el mismo truco de `class="contents"`.
  - Se mantiene el único punto de tabulación por celda (los `<a>`/`<span>` siguen siendo focables de uno en uno con Tab, no el patrón de flechas de un `grid` ARIA completo) — el encargo pedía completar `role="row"`/`gridcell`/`columnheader`, no implementar navegación por flechas, que sería un cambio de interacción mayor no pedido.
  - Tests: `tests/Feature/Booking/PublicBookingTest.php` › «the calendar grid groups its weeks in role rows and marks every day a gridcell», `tests/Feature/Admin/AgendaCalendarAccessibilityTest.php` › «the week view groups its cells in role rows and gridcells» y «the month view groups its cells in role rows and gridcells» (cuentan `role="row"`/`"gridcell"`/`"presentation"` exactos para enero de 2030).
- **Pendiente a mano:** la comprobación con lector de pantalla real (VoiceOver/NVDA) que pedía el punto 2 de «Para comprobar en el navegador» sigue sin hacerse — la hace el coordinador.
- **Impacto:** el patrón ARIA `grid` exige que sus hijos directos tengan `role="row"` y que cada fila contenga celdas con `role="gridcell"` (o `columnheader`/`rowheader`); sin esa estructura, un lector de pantalla no tiene por qué anunciar la tabla correctamente (puede ignorar el rol, o anunciar coordenadas de fila/columna incoherentes), que es justo el riesgo señalado en el encargo de esta revisión: un `grid` ARIA mal formado es peor que no poner ningún rol. Aquí:
  - En `_month.blade.php`, el contenedor con `role="grid"` tiene como hijos directos 7 `<div role="columnheader">` y hasta 42 `<a>`/`<div>` de día **sin ningún `role="row"` que los agrupe por semana ni `role="gridcell"` en las celdas**.
  - En `_week.blade.php` (rejilla de escritorio), es la misma forma: `role="grid"` envolviendo 7 `<div role="columnheader">` (uno por columna) y 7 `<div>` de contenido sin `role="row"` ni `role="gridcell"`.
  - Además, cada celda es un `<a>` individualmente enfocable con Tab (hasta 49 paradas en Mes), en vez del patrón de rejilla habitual de un único punto de tabulación con flechas entre celdas — operable con teclado de forma básica, pero no el patrón `grid` que el rol anunciado hace esperar.
  - Este mismo patrón incompleto ya existe en el calendario público (`resources/views/pages/partials/reservas-calendar.blade.php:23-25`), de donde T031 lo reutilizó a propósito (así lo dice el propio `.ai/tasks/reservas/T031-vista-mes.md`); no es una regresión nueva de este diff, pero PRF-107 (nuevo, de esta entrega) pide explícitamente «semántica de rejilla accesible», y ahora mismo ninguna de las tres rejillas la tiene completa.
- **Evidencia:** `resources/views/admin/agenda/_month.blade.php:11-39`, `resources/views/admin/agenda/_week.blade.php:44-68`, y (fuera de este diff, para contexto) `resources/views/pages/partials/reservas-calendar.blade.php:23-39`. Los tests existentes solo comprueban la presencia de las cadenas `role="grid"`/`role="columnheader"` (`AgendaMonthViewTest.php:116-117`, `AgendaCalendarAccessibilityTest.php:43`), nunca `role="row"` ni `role="gridcell"` ni el comportamiento con un lector de pantalla real.
- **Recomendación:** o bien completar la estructura ARIA (agrupar cada semana en un `role="row"` y dar `role="gridcell"` a cada celda de día, manteniendo el `<a>` dentro de la celda), o bien renunciar al rol `grid`/`columnheader` y dejar una lista o rejilla visual sin semántica de tabla (un conjunto de enlaces con `aria-label` descriptivo, que es lo que realmente hay). Dado que el patrón se repite en tres sitios (reservas pública, Semana y Mes del panel), conviene resolverlo una vez y aplicarlo a los tres, no solo a los dos de este diff.

## Low

### L1. `$weekEnd` tiene dos significados distintos entre `index()` y `weekData()`, sin que el nombre lo distinga

- **Estado:** resolved
- **Resolución:** la variable local de `index()` (el domingo, último día inclusivo, usado solo para la etiqueta «Semana del ... al ...» y el `aria-label` de la rejilla) se renombró a `$weekLastDay`. La de `weekData()` (el lunes siguiente, límite exclusivo que usan las consultas) conserva el nombre `$weekEnd`, ya coherente con el `$monthEnd` exclusivo de `monthData()`. La clave que se pasa a la vista sigue llamándose `weekEnd` (ahí no había ambigüedad: todo lo que la usa en Blade trata ese valor como el último día inclusivo), así que ningún archivo de vista cambió por este punto.
- **Impacto:** mantenibilidad, no correctness actual. `AgendaController::index()` pasa a la vista `'weekEnd' => $weekStart->addDays(6)` (domingo, el **último día** de la semana, usado solo para mostrar «Semana del dd/mm al dd/mm/aaaa»). Dentro de `weekData()` (`AgendaController.php:92`), la variable local `$weekEnd = $weekStart->addWeek()` es el **límite exclusivo** usado en las consultas (lunes siguiente). Ambas se llaman igual pero representan fechas distintas (una diferencia de un día); quien toque este código en el futuro para, por ejemplo, añadir otra consulta por rango de semana, puede coger por error la variable equivocada.
- **Evidencia:** `app/Http/Controllers/Admin/AgendaController.php:33` (`index()`, inclusivo) frente a `:92` (`weekData()`, exclusivo); ambas conviven en el mismo archivo con el mismo nombre.
- **Recomendación:** renombrar una de las dos para que el nombre refleje la diferencia (por ejemplo `weekLastDay` para la inclusiva de `index()`, dejando `weekEnd` como el límite exclusivo que ya usan las consultas).

### L2. La tira de 7 días de Semana (móvil) queda por debajo de 44 px de ancho a 360 px, por un margen pequeño

- **Estado:** anotado, sin cambio de código. Decisión del usuario, 2026-10-05, junto con M2: acepta anchos de 39-43 px a 360 px, manteniendo los 44 px de alto. Anotado también en `.ai/specs/reservas.md`, PRF-105.
- **Impacto:** igual que M2 pero para `_week.blade.php` (tira móvil, sin el contenedor con borde/relleno extra de Mes): `360 − 32 (px-4 del main) − 24 (6 huecos de `gap-1`) = 304 px`, entre 7 columnas ≈ **43,4 px**, por debajo de los 44 px que pide PRF-101 («zona táctil de al menos 44 px cada uno»), aunque por un margen pequeño (bajo el milímetro) que puede no ser perceptible al tacto y podría deberse a un redondeo en mi cálculo. A 375 px (el ancho que usan las verificaciones manuales de T029-T032) el mismo cálculo da ≈45,6 px, por encima del mínimo — el problema, de existir, es específico de viewports de 360-374 px.
- **Evidencia:** `resources/views/admin/agenda/_week.blade.php:11` (`grid grid-cols-7 gap-1`), `:21` (clase de la pastilla, solo `min-h-11`, sin ancho mínimo); `resources/views/layouts/admin.blade.php:150`. Cálculo a mano, no confirmado en un navegador real.
- **Recomendación:** pedir al coordinador que lo mida con el inspector a 360 px (ver «Para comprobar en el navegador»); si se confirma por debajo de 44 px, el ajuste es menor (por ejemplo `gap-0.5` en vez de `gap-1`, o quitar 1-2 px de relleno lateral del `main` en este breakpoint).

### L3. Cuando la semana mostrada cruza de un mes a otro, la rejilla de escritorio no indica el cambio de mes

- **Estado:** resolved
- **Resolución:** la cabecera de cada columna (`_week.blade.php`) compara el mes del día (`$d['date']->month`) con el de `$weekStart->month`; si son distintos muestra `d/m` en vez de solo `d`. Test: `tests/Feature/Admin/AgendaCalendarAccessibilityTest.php` › «the week view desktop grid shows the month once the week crosses into it» (semana del 28 de enero al 3 de febrero de 2030: «lunes 28» frente a «viernes 01/02»).
- **Impacto:** en la rejilla de escritorio de Semana, la cabecera de cada columna solo muestra el nombre del día y el número de día (`{{ $weekdays[...] }} {{ $d['date']->format('d') }}`), sin el mes. Si la semana mostrada cruza de un mes a otro (por ejemplo, lunes 26 de enero a domingo 1 de febrero), las columnas se leen «lunes 26, martes 27, …, domingo 1» sin ninguna pista de que el «1» ya es de febrero — el título de la página sí lo resuelve («Semana del 26/01 al 01/02/2026»), pero no la cabecera de cada columna. Es una ambigüedad menor, no exigida explícitamente por ningún PRF.
- **Evidencia:** `resources/views/admin/agenda/_week.blade.php:47-48`.
- **Recomendación:** opcional. Si se quiere resolver, mostrar `d/m` en vez de solo `d` cuando el mes de la columna no coincida con el de `$weekStart` (o siempre, por simplicidad).

---

## Hallazgos del coordinador en el navegador (N1-N3, 2026-10-05)

### N1. El título de Semana salía «Semana Del ... Al ...», con mayúsculas de más

- **Estado:** resolved
- **Resolución:** la causa era una clase CSS `capitalize` genérica en el `<p>` del título (`index.blade.php`), pensada para el único caso de Día (una sola palabra, el nombre del día: «jueves» → «Jueves»), pero `text-transform: capitalize` pone en mayúscula la primera letra de **cada** palabra, no solo la del texto completo — en Semana («Semana del ... al ...») capitalizaba también «del» y «al». Se quitó la clase `capitalize` y cada etiqueta se compone ya correctamente en PHP: Día usa `ucfirst($weekdays[...])` explícito (antes dependía de la clase CSS), Semana ya era literal («Semana del...al...», sin necesidad de `ucfirst`) y Mes ya usaba `ucfirst(...)` desde T031. Tests: `tests/Feature/Admin/AgendaCalendarAccessibilityTest.php` › «día's label capitalizes only the weekday's first letter», «semana's label does not capitalize "del"/"al"», «mes's label capitalizes only the month's first letter».

### N2. En escritorio, las pestañas, Anterior/Siguiente/Hoy y «Ir a la fecha» ocupaban tres filas apiladas

- **Estado:** resolved
- **Resolución:** las tres piezas de navegación (`index.blade.php`) se envuelven ahora en un contenedor `flex flex-col gap-4 md:flex-row md:items-center md:justify-between md:flex-wrap`: apiladas en móvil (igual que antes), en una sola fila desde `md` (768 px) hacia arriba. El bloque Anterior/Siguiente/Hoy + formulario de fecha, que antes apilaba sus dos filas internas con `space-y-3`, pasa a `flex flex-col gap-3 md:flex-row md:items-center md:gap-4` para que también se alineen en horizontal dentro de ese mismo grupo. Test: `tests/Feature/Admin/AgendaCalendarAccessibilityTest.php` › «the agenda nav lays out in a single row from md up».

### N3. En la rejilla de escritorio de Semana, el texto de las citas era muy pequeño y no era un enlace

- **Estado:** resolved
- **Resolución:** cada cita de la rejilla de escritorio (`_week.blade.php`) pasa de `text-xs` suelto, sin enlace, a un `<a>` de `text-sm` con `min-h-11` (zona táctil de 44 px) y un `aria-label` con la hora, el servicio y la clienta («11:00 Peinado, Marta Ruiz»). El enlace va a editar la cita cuando es posible (confirmada y futura, las mismas condiciones que ya usa vista Día para ofrecer «Editar»); si no (cancelada o ya empezada), enlaza a su vista Día, donde se puede ver el resto del contexto. Una cita cancelada añade el texto «(Cancelada)» además del tachado, para no depender solo del estilo visual. Tests: `tests/Feature/Admin/AgendaWeekViewTest.php` › «the week view desktop grid links each appointment to editing it, with an aria-label and a 44px target» y «the week view desktop grid marks a cancelled appointment with text, not only strike-through».

---

## Para comprobar en el navegador (lo hace el coordinador)

A 375 px y en escritorio (≥1024 px), salvo que se indique otro ancho. M2/L2 ya no están en esta lista: el usuario decidió el ancho de celda sin necesitar más medición.

1. **M3 — lector de pantalla.** Con VoiceOver/NVDA, entrar en la rejilla de Mes y en la de Semana (escritorio) y comprobar qué anuncia el lector ahora que llevan `role="row"`/`"gridcell"`/`"columnheader"` completos: si anuncia coordenadas de fila/columna con sentido o produce un anuncio confuso.
2. **H1 — cierres en Semana.** Crear un cierre total y uno de capacidad reducida que caigan dentro de la semana mostrada (desde `/admin/cierres`) y confirmar, a 375 px y 1024 px, que el día se marca «Cerrado»/«Capacidad reducida» tanto en la tira móvil como en la rejilla de escritorio, y que una cita confirmada que sobreviva al cierre se sigue viendo.
3. **M1 — «volver».** Desde Semana y desde Mes, crear una cita, editar una y cancelar una; confirmar que las tres acciones devuelven a la misma vista y fecha de origen, no siempre a Día.
4. **N1 — título de Semana.** Confirmar que ya no dice «Del»/«Al» en mayúscula.
5. **N2 — navegación en una fila.** A 1024 px, confirmar que las pestañas, Anterior/Siguiente/Hoy y el formulario de fecha caben en una sola fila sin solaparse; a 375 px, que siguen apiladas como antes.
6. **N3 — citas de la rejilla de Semana.** A 1024 px, confirmar que el texto de las citas se lee bien, que tocar una cita confirmada futura abre su edición, que una pasada/cancelada lleva a su día, y que una cancelada se lee «(Cancelada)» además de tachada.
7. **Selector Día/Semana/Mes (PRF-099) y tres pestañas.** Verificar visualmente que las tres pestañas se ven bien a 375 px (sin solaparse ni partirse en dos líneas) y a 1024 px.
8. **Mes (PRF-102, PRF-103).** A 375 px y 1024 px, confirmar que el número de citas y «Cerrado» se leen bien dentro de cada celda sin desbordar, y que tocar cualquier día (con o sin citas) abre su vista Día.
9. **Hoy y «hoy»/«· Hoy» (PRF-106, T032).** A 375 px, confirmar que la palabra «hoy» dentro de la pastilla de Semana no desborda. A 1024 px, confirmar que «· Hoy» no rompe la cabecera de columna en un día con nombre largo (miércoles).
10. **Navegación Anterior/Siguiente/Hoy y formulario «Ir a la fecha» (PRF-104).** En cada vista, comprobar que los controles mueven la unidad correcta (día/semana/mes) y que el formulario de fecha conserva la vista activa al enviarse.
