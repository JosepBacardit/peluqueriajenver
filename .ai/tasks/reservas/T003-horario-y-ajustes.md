# T003 — Horario semanal y ajustes de la reserva

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-017, PRF-018, PRF-019, PRF-020, PRF-009
- **Depende de:** T001
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `medium`
- **Motivo:** son formularios con validación cruzada entre tramos.
- **Estado:** done
- **PR / rama:** PR 1, `feature/booking-admin-foundation`

## Plan
1. Migración `create_opening_hours_table` (`weekday` ISO 1–7, `opens_at` y `closes_at` como `time`) que inserta martes a sábado de 09:00 a 19:00.
2. Migración `create_booking_settings_table` (fila única) con `capacity`, `slot_interval_minutes`, `min_notice_minutes`, `max_advance_days` y `cancellation_limit_hours`. Inserta los valores iniciales.
3. Modelos `OpeningHour` y `BookingSetting::current()`.
4. `Admin\OpeningHoursController` (`edit` y `update`): un formulario de 7 días × 2 tramos. `OpeningHoursRequest` valida el formato `H:i`, los múltiplos de 5, que el fin sea posterior al inicio, los tramos incompletos y el solape. Se guarda todo en una transacción, reemplazando las filas.
5. `Admin\BookingSettingsController` (`edit` y `update`) con `BookingSettingsRequest`.

## Criterios de aceptación
- Se guardan dos tramos. Los tramos imposibles se rechazan sin cambios (PRF-017, PRF-018).
- El horario inicial es el de la migración (PRF-019).
- Los ajustes respetan sus límites y valores iniciales (PRF-020).

## Plan de pruebas
`tests/Feature/Admin/OpeningHoursManagementTest.php` y `tests/Feature/Admin/BookingSettingsManagementTest.php`.

## Verificación
`docker compose exec -T app php artisan test --compact` · `docker compose exec -T app vendor/bin/pint --dirty --format agent`

## Riesgos
Las migraciones insertan datos. Solo son aditivas y van con tablas nuevas.
