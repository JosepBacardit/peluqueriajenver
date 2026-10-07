# T035 — Algoritmo de carriles

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-109, PRF-110
- **Depende de:** T034
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `high`
- **Motivo:** es la pieza más intrincada del encargo (varios escenarios de solape), pero es lógica de maquetación nueva sobre datos ya cargados, no una regla de negocio compartida — no toca `AvailabilityCalculator`.
- **Estado:** done
- **PR / rama:** `feature/agenda-timeline-grid`

## Objetivo

Repartir las citas confirmadas de un día en carriles fijos (uno por unidad de capacidad), con el algoritmo de primer carril libre, añadiendo carriles de más cuando se supera la capacidad.

## Cambios

`app/Booking/AppointmentLaneAssigner.php` (nuevo): `assign(Collection $appointments, int $capacity): array{lanes, maxLanes, overCapacity}`.
- Filtra a solo citas confirmadas (una cancelada no ocupa carril, PRF-110) y las ordena por hora de inicio.
- Primer carril libre: recorre los carriles existentes y usa el primero cuya última cita ya haya terminado cuando empieza la nueva (la misma regla de solape de todo el proyecto: terminar justo cuando otra empieza no es solape); si ninguno está libre, añade un carril nuevo.
- `maxLanes` = `max($capacity, carriles realmente usados)`: la rejilla siempre pinta al menos la capacidad en carriles, incluso sin ninguna cita.
- `overCapacity`: qué citas quedaron en un carril igual o por encima de la capacidad (PRF-109, el caso de «Guardar igualmente»).
- Los carriles son un índice numérico sin ningún significado más allá del orden de asignación: no identifican a ninguna peluquera.

## Evidencia

`tests/Feature/Booking/AppointmentLaneAssignerTest.php`: una cita sola, dos solapadas, citas consecutivas sin hueco (comparten carril), un carril que se libera y se reutiliza, más citas simultáneas que la capacidad (carriles extra y `overCapacity`), una cancelada (no ocupa carril), un día vacío, y que el resultado no depende del orden de entrada.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact --filter=AppointmentLaneAssignerTest` → 8 tests en verde (23 aserciones) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` → `{"result":"pass"}`.
