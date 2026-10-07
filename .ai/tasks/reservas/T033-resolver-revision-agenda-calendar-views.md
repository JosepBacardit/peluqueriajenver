# T033 — Resolver la revisión de las vistas de la agenda

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-105 (ampliado: cierres de capacidad reducida en Semana, decisión sobre el ancho de celda)
- **Depende de:** T029–T032
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** resuelve 1 hallazgo alto, 3 medios y 3 bajos de `.ai/reviews/agenda-calendar-views.md`, más 3 hallazgos del coordinador en el navegador (N1-N3), tocando controlador, cuatro vistas (incluido el calendario público) y el flujo de creación/edición/cancelación de citas.
- **Estado:** done
- **PR / rama:** `feature/agenda-calendar-views`

## Objetivo

Resolver, con la *skill* `fix-review`, los hallazgos de la revisión independiente de T029–T032 (`.ai/reviews/agenda-calendar-views.md`) y los tres hallazgos adicionales que el coordinador vio en el navegador, aprobados por el usuario.

## Resolución de cada hallazgo

- **H1** (alto). Semana no marcaba «Cerrado» un día con un cierre total, a diferencia de Mes. `weekData()` ahora calcula `isClosed`/`hasPartialClosure` por día a partir de los bloques ya cargados (sin consulta nueva); `_week.blade.php` lo refleja en la tira móvil (texto y `aria-label`) y en la rejilla de escritorio (que además sigue listando las citas supervivientes al cierre, en vez de ocultarlas).
- **M1** (medio). Crear, mover o cancelar una cita desde Semana o Mes volvía siempre a Día. Nuevo parámetro `volver` (`"vista:fecha"`), validado por `AgendaController::volverFromQuery()` contra la misma lista blanca que `vista`/`fecha`; nunca se usa como URL cruda, siempre como *array* de parámetros de `route()`, así que no puede ser una redirección abierta. Hilado por `create`/`edit` (campo oculto) y `store`/`update`/`cancel`/`notMovableResponse` (redirect).
- **M2** (medio) y **L2** (bajo). Anchos de celda de 39-43 px a 360 px. **Aceptados por el usuario, sin cambio de código**; anotado en `.ai/specs/reservas.md` PRF-105.
- **M3** (medio). Semántica ARIA de rejilla incompleta (`role="grid"`/`"columnheader"` sin `"row"`/`"gridcell"`). Completada en las tres rejillas que la usan: `_month.blade.php`, `_week.blade.php` (rejilla de escritorio) y `pages/partials/reservas-calendar.blade.php` (calendario público, no tocado por T029-T032 pero con el mismo patrón incompleto). Los días se agrupan en semanas con `<div role="row" class="contents">` (la utilidad `display: contents` saca el `<div>` del cálculo de `grid-cols-7`, así que la disposición visual no cambia) y cada día lleva `role="gridcell"`.
- **L1** (bajo). `$weekEnd` con dos significados en `AgendaController`. La variable local de `index()` (inclusivo) se renombra a `$weekLastDay`; la de `weekData()` (exclusivo) conserva el nombre.
- **L3** (bajo). La rejilla de escritorio de Semana no avisaba del cambio de mes. La cabecera de cada columna muestra `d/m` en vez de solo `d` cuando el mes del día no coincide con el de `$weekStart`.
- **N1** (coordinador). El título de Semana salía «Semana Del ... Al ...». Causa: una clase CSS `capitalize` genérica, pensada para Día, también ponía en mayúscula «del»/«al». Se quitó; cada etiqueta se compone ya con el casing correcto en PHP.
- **N2** (coordinador). Las pestañas, Anterior/Siguiente/Hoy y «Ir a la fecha» ocupaban tres filas en escritorio. Envueltos en un contenedor `flex flex-col ... md:flex-row md:items-center md:justify-between`, en una sola fila desde `md` (768 px).
- **N3** (coordinador). El texto de las citas de la rejilla de Semana (escritorio) era pequeño y no era un enlace. Cada cita pasa a `text-sm`, `min-h-11`, con `aria-label` (hora, servicio, clienta) y enlace a editar (si es posible) o a su vista Día; una cancelada añade el texto «(Cancelada)» además del tachado.

## Cambios

`app/Http/Controllers/Admin/AgendaController.php`: `weekData()` calcula cierres por día (H1); `index()` renombra la variable inclusiva a `$weekLastDay` y añade `volver` a los datos de la vista (M1); nuevos métodos `volverFromQuery()` e `isValidDateString()` (M1).

`app/Http/Controllers/Admin/AppointmentController.php`: `create`/`edit`/`store`/`update`/`cancel`/`notMovableResponse` leen y propagan `volver` (M1).

`resources/views/admin/agenda/index.blade.php`: título sin la clase `capitalize` (N1), navegación envuelta en una fila en escritorio (N2), `volver` en los enlaces «Nueva cita».

`resources/views/admin/agenda/_day.blade.php`: `volver` en «Editar» y en un campo oculto de «Cancelar».

`resources/views/admin/agenda/_week.blade.php`: cierres por día (H1), `role="row"`/`"gridcell"` (M3), mes en la cabecera al cruzar de mes (L3), citas como enlaces de 44 px con `aria-label` (N3).

`resources/views/admin/agenda/_month.blade.php` y `resources/views/pages/partials/reservas-calendar.blade.php`: `role="row"`/`"gridcell"` (M3).

`resources/views/admin/appointments/create.blade.php` y `edit.blade.php`: campo oculto `volver` y «Volver a la agenda» respetándolo (M1).

`.ai/specs/reservas.md`: nota en PRF-105 sobre el cierre de capacidad reducida en Semana y la decisión de ancho de celda (M2/L2).

## Evidencia

Tests nuevos: `AgendaWeekViewTest` (H1 ×2, N3 ×2), `AdminAppointmentTest` (M1 ×4: campo oculto válido/inválido, `store`/`cancel` vuelven a `vista`/`fecha`), `AdminRescheduleAppointmentTest` (M1 ×4: campo oculto, `update` vuelve a `vista`/`fecha`, valor manipulado nunca es una redirección abierta, `notMovableResponse` respeta `volver`), `AgendaCalendarAccessibilityTest` (M3 ×2, L3, N1 ×3, N2), `PublicBookingTest` (M3 del calendario público).

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` → suite completa en verde (ver hashes y número exacto en el informe final al coordinador) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` → sin cambios · `docker compose exec -T node npm run build` → sin errores; **no se ha reiniciado el contenedor `node`**.

Pendiente a mano (lo hace el coordinador): la lista actualizada de «Para comprobar en el navegador» de `.ai/reviews/agenda-calendar-views.md` (lector de pantalla para M3, cierres reales para H1, `volver` para M1, y los tres puntos N1-N3).
