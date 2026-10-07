# T048 — Panel: crear, editar y mover citas con varios servicios, y «Cabe» con varios

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-129, PRF-132 (y PRF-046, PRF-077, PRF-078, PRF-120, PRF-121, PRF-123 y PRF-124 ajustados)
- **Depende de:** T046
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `high`
- **Motivo:** formularios y agenda del panel sobre la interfaz de T046. La concurrencia ya está resuelta en las Actions.
- **Estado:** done
- **PR / rama:** `feature/multi-service-appointments`, commit `121ed38`.

## Resolución

`create`/`edit` cambian el `<select>` por casillas (`service_ids[]`, hasta el máximo, con `aria-invalid`/`aria-describedby`); `edit` marca ya todos los servicios de la cita, no solo el primero (el error que T046 avisó). `StoreAdminAppointmentRequest`/`UpdateAdminAppointmentRequest` validan `service_ids`/`service_ids.*`; `slotKey()` identifica la lista ordenada (no un id suelto), así que «Guardar igualmente» sigue encajando aunque el reenvío llegue en otro orden.

Agenda: `AgendaController::serviciosFromQuery()` sustituye a `servicioFromQuery()` y devuelve una `Collection` (1 a `MAX_SERVICES`, inválidos ignorados en silencio); «Cabe» usa la suma de duraciones. El selector pasa de `<select>` a un `<details>` con casillas (cómodo a una mano, sin JavaScript); cada enlace que lleva `servicio` usa un escalar con un solo servicio y el array solo con más de uno, para no alargar las URL del caso más común.

Tests: `tests/Feature/Admin/MultiServiceAdminTest.php` (nuevo, 7 casos) y ampliaciones de `AgendaServiceFilterTest`, `AdminAppointmentTest`, `AdminRescheduleAppointmentTest`.

## Objetivo

Crear, editar y mover citas con de 1 a 5 servicios desde el panel. Además, el selector «Cabe» de la agenda debe admitir varios servicios y marcar dónde cabe la suma de sus duraciones (decisión del usuario, 2026-10-06).

## Interfaz de T046 que hay que usar

- `CreateAppointment::handle(Collection $services, …)` y `RescheduleAppointment::handle(Appointment $appointment, Collection $services, …)`. `RescheduleOutcome::$rescheduled` ya cuenta un cambio de lista.
- `$appointment->services` (preseleccionar las casillas), `$appointment->items` (datos congelados) y `$appointment->durationMinutes()`.
- `Appointment::MAX_SERVICES` y `AvailabilityCalculator::fittingStartMinutes()` con la suma.

## Plan

1. **`create`/`edit`:**
   - casillas en lugar del `<select>`, cada una con su duración y con `aria-invalid`/`aria-describedby` como el resto de campos;
   - el total visible;
   - `service_ids[]` de 1 a `MAX_SERVICES`, `distinct` y activos. En `edit` se admiten también los servicios propios de la cita aunque estén desactivados;
   - **importante:** el `edit` actual solo envía el primer servicio, así que una cita con varios perdería el resto al guardarse. Este cambio lo corrige.
2. **«Guardar igualmente»:** `slotKey()` tiene que incluir la lista de ids (ordenada), no un solo `service_id`.
3. **Agenda («Cabe», PRF-132):**
   - `servicio[]` de 1 a `MAX_SERVICES`;
   - `servicioFromQuery()` pasa a devolver una lista de servicios activos (los ids no válidos se ignoran en silencio);
   - la duración es la suma, con `fittingStartMinutes()`;
   - se mantiene la persistencia en toda la navegación (PRF-123), incluida una visita a Mes, y `servicio[]` nunca se mezcla con `volver`;
   - «Nueva cita» preselecciona todos;
   - el selector, sin JS, puede ser una lista de casillas plegable (`<details>`) con «Ver».
   - El número de consultas de la agenda tiene que seguir siendo fijo (hay tests).
4. **Mover (T017):** el aviso de hueco no disponible y el correo de cambio usan la suma y la lista.

## Plan de pruebas

En `AdminAppointmentTest`, `AdminRescheduleAppointmentTest` y `AgendaServiceFilterTest`:

- crear y editar con 2 servicios;
- quitar y añadir servicios conservando los datos congelados;
- un servicio desactivado propio de la cita se acepta y uno ajeno se rechaza;
- 6 servicios o un servicio repetido se rechazan;
- la `slotKey` con varios servicios;
- «Cabe» con 2 servicios marca solo donde cabe la suma;
- `servicio[]` se conserva al navegar;
- consultas fijas;
- preselección múltiple en «Nueva cita».