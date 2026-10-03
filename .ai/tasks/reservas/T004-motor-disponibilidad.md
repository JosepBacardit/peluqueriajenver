# T004 — Citas y motor de disponibilidad

- **Tipo:** ARCHITECTURAL
- **Puntos de referencia:** PRF-015, PRF-025, PRF-026
- **Depende de:** T002, T003
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** es la lógica crítica: solapes, capacidad, cierres y concurrencia.
- **Estado:** pending
- **PR / rama:** PR 2, `feature/booking-availability`

## Plan
1. Migración `create_appointments_table`:
   - `service_id`, `service_name`, `starts_at`, `ends_at`;
   - `customer_name`, `customer_phone`, `customer_email` (nullable), `notes`;
   - `status` y `source` (*enums* en PHP), `token` (único), `cancelled_at`, `privacy_accepted_at`;
   - `customer_notified_at`, `salon_notified_at`, `timestamps`;
   - índice en `(starts_at, ends_at)`.
2. Migración `create_schedule_blocks_table` con `starts_at`, `ends_at`, `capacity_reduction` (nullable = cierre total) y `reason`. Va en esta tarea porque el motor la necesita; su panel está en T005.
3. *Enums* `AppointmentStatus` (`Confirmed`, `Cancelled`) y `AppointmentSource` (`Web`, `Admin`).
4. `App\Booking\AvailabilityCalculator`:
   - `availableStartTimes(Service, CarbonImmutable $day, CarbonImmutable $now)` aplica las reglas 1–3;
   - `isBookable(int $duration, CarbonImmutable $start, CarbonImmutable $now, bool $publicRules)`.
   - La capacidad se evalúa solo en el inicio de la cita y en los inicios de citas o cierres que caen dentro, que es donde puede subir la ocupación. Así el cálculo es exacto sea cual sea el intervalo.
5. `App\Actions\CreateAppointment`: transacción, `lockForUpdate()` sobre la fila de `booking_settings`, nueva comprobación con el motor y alta. Lanza `SlotUnavailableException`. Copia `service_name` y calcula `ends_at`.
6. `App\Actions\CancelAppointment`.

## Criterios de aceptación
- Los seis ejemplos resueltos de la especificación (PRF-025).
- Una hora ocupada entre la consulta y el alta lanza la excepción y no crea la cita (PRF-026).
- Cambiar la duración del servicio no cambia `ends_at` (PRF-015).

## Plan de pruebas
- `tests/Feature/Booking/AvailabilityCalculatorTest.php`: los 6 ejemplos, horario partido, día cerrado, antelación mínima y máxima, intervalo, canceladas que no cuentan y cambio de hora de verano.
- `tests/Feature/Booking/CreateAppointmentTest.php`: nueva comprobación dentro de la transacción y copia de nombre y duración.
- Son tests de *feature* porque necesitan base de datos.

## Verificación
`docker compose exec -T app php artisan test --compact`

## Riesgos
SQLite ignora `FOR UPDATE`. El bloqueo real solo actúa en MySQL; el test demuestra la nueva comprobación, y el bloqueo se revisa en el código.
