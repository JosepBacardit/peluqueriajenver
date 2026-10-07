# T062 — Esperas: modelo de datos y motor de disponibilidad

- **Tipo:** ARCHITECTURAL
- **Puntos de referencia:** PRF-151 a PRF-157 y PRF-125 (modificado)
- **Depende de:** T061
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `high`
- **Motivo:** cambia el modelo de ocupación del que dependen la reserva online, el panel y «Cabe»; es el núcleo que evita el *overbooking*.
- **Estado:** done
- **PR / rama:** `feature/service-wait-times` (desde `main` `495f752`).

## Objetivo

Que la disponibilidad cuente solo los tramos activos de cada cita, de modo que la espera de un servicio (p. ej. la exposición de un tinte) deje la plaza libre para otra clienta, sin cambiar nada para los servicios y las citas sin esperas.

## Resolución

- **Migración nueva** `2026_10_07_120000_add_waits_to_services_and_appointments.php`, solo aditiva: columna `waits` (JSON, nula) en `services`, `appointment_services` y `appointments`. No toca las `2026_10_03_*`, ya ejecutadas en producción.
- **`App\Booking\TimeProfile`:** duración y esperas de un servicio o de una cita.
  - `fromServices()` encadena servicios o copias congeladas (modelos o arrays) en el orden recibido;
  - `fromAppointment()` lee `appointments.waits`;
  - `activeOffsets()`/`activeIntervals()` devuelven los tramos activos;
  - `equals()` y `waitsForStorage()`, que devuelve `null` si no hay esperas;
  - el constructor rechaza esperas fuera de sitio (`InvalidArgumentException`), como protección de programación; la validación de cara al salón es T063;
  - `MAX_WAITS_PER_SERVICE = 2`.
- **`AvailabilityCalculator`:**
  - `isAvailable`, `unavailabilityReason`, `availableStartTimes`, `daysWithAvailability` y `fittingStartMinutes` aceptan `TimeProfile|int`; un entero es una duración sin esperas, así que los llamadores y tests existentes no cambian;
  - la ocupación se carga como intervalos activos (`busyIntervals`, columna `waits` incluida, sin consultas nuevas);
  - `capacityProblem()` comprueba `Closed` en toda la cita, esperas incluidas, y `Full` solo en sus tramos activos;
  - los momentos revisados son el inicio de la cita, el inicio de cada tramo activo y los inicios de tramos activos ajenos y de cierres;
  - con un solo tramo, el resultado es idéntico al de antes.
- **`CreateAppointment`:** comprueba con `TimeProfile::fromServices()` y guarda `waits` en la cita junto a `ends_at`. `AppointmentService::snapshotOf()` congela `waits`.
- **`RescheduleAppointment`:**
  - si se mantienen los mismos servicios, conserva el perfil de la cita;
  - si cambian, los servicios que se mantienen aportan su copia congelada (con `waits`) y los nuevos, la actual;
  - `keepsSlot` compara el perfil completo (`equals`), no solo la duración.
- **`BookingController` (público) y `AgendaController` («Cabe»):** pasan `TimeProfile::fromServices()` en vez de la suma de duraciones. Es una línea en cada uno, necesaria para que la web ofrezca lo que `CreateAppointment` acepta y para mantener PRF-122.
- **`AppointmentFactory::withServices()`:** encadena también las esperas.

## Plan de pruebas

- `tests/Feature/Booking/ServiceWaitTimesTest.php` (41 tests):
  - tramos activos y encadenado;
  - esperas no válidas;
  - los 7 ejemplos resueltos de PRF-152;
  - `Closed` frente a `Full`;
  - regla 1 con la espera dentro;
  - `daysWithAvailability`;
  - la página pública ofrece las 10:30;
  - congelado al reservar y al cambiar el servicio;
  - citas sin esperas previas;
  - mover conservando o cambiando servicios;
  - `keepsSlot` por perfil;
  - `fittingStartMinutes`;
  - comparación minuto a minuto con un cálculo por fuerza bruta en 15 días con semilla.
- `AvailabilityFitForServiceTest` › «it always agrees with isAvailable for the panel»: ampliado con citas con espera y con dos perfiles candidatos con esperas.
- Suite completa: 801 tests en verde (760 antes). Pint pasado sobre los archivos tocados.
- Migración ejecutada en la base local (MySQL): el JSON se lee y se escribe igual, y no queda ningún servicio con esperas.

## Fuera de esta tarea

- La agenda (`AppointmentLaneAssigner`, `DayTimeline`) sigue asignando la cita entera a un carril (T064). Mientras ningún servicio tenga esperas (no se pueden configurar hasta T063), no cambia nada visible.
- En «Cabe», `DayTimeline::markServiceFit()` resalta el carril con la duración total; el veredicto de capacidad ya usa las esperas.
