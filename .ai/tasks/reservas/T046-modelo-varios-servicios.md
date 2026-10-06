# T046 — Modelo de datos y Actions con varios servicios por cita

- **Tipo:** ARCHITECTURAL
- **Puntos de referencia:** PRF-125, PRF-126, PRF-131 (y la base de PRF-127 a PRF-130 y PRF-132)
- **Depende de:** T045 (rama `feature/agenda-service-filter`, PR #8)
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `high`
- **Motivo:** cambia el modelo de datos de la cita y las dos Actions que la escriben bajo el bloqueo de concurrencia (PRF-026, PRF-086).
- **Estado:** done
- **PR / rama:** `feature/multi-service-appointments`, desde `feature/agenda-service-filter`. El agente principal la junta en la PR #8 antes de desplegar.

## Objetivo

Que una cita guarde de 1 a 5 servicios, con sus datos congelados, y que crearla y moverla funcione con listas de servicios sin perder ninguna garantía de concurrencia. Con un solo servicio, la interfaz no cambia todavía: eso es T047 a T049.

## Esquema

```
appointments
  id, services_label string(512), starts_at, ends_at,
  customer_name, customer_phone, customer_email, notes, status, source,
  token, cancelled_at, privacy_accepted_at, customer_notified_at,
  salon_notified_at, timestamps
  (sin service_id ni service_name)

appointment_services
  id
  appointment_id   FK → appointments, cascadeOnDelete
  service_id       FK → services, restrictOnDelete
  position         unsignedTinyInteger (1..5)
  service_name     string(100)          congelado
  duration_minutes unsignedSmallInteger congelado
  price_cents      unsignedInteger null congelado; solo interno
  timestamps
  unique(appointment_id, position)
```

- `services_label` es el resumen congelado: los nombres unidos por « + », en orden. Caben 5 nombres de hasta 100 caracteres más los separadores en 512. Se escribe en la misma operación que los servicios, para que la agenda, sus tarjetas y los correos lo muestren sin cargar los servicios.
- `starts_at` y `ends_at` abarcan la suma de duraciones, y la cita ocupa una plaza durante todo ese tiempo.
- **Migraciones:** como nunca se han ejecutado en producción, se han editado las de la PR en lugar de añadir una de conversión.
  - `2026_10_03_150000_create_appointments_table`: quita `service_id` y `service_name` y añade `services_label`.
  - `2026_10_03_150050_create_appointment_services_table`: nueva.

## Interfaz para T047–T049

- `Appointment::MAX_SERVICES = 5`: el único sitio donde está el máximo (PRF-125). Lo usan las validaciones, la reserva pública, el panel y el selector «Cabe».
- `Appointment::SERVICES_LABEL_SEPARATOR = ' + '`.
- `Appointment::items(): HasMany<AppointmentService>`: los servicios tal como se reservaron, ordenados por `position`. Es lo que se lista en `/cita`, los correos y la ficha.
- `Appointment::services(): BelongsToMany<Service>`: los `Service` actuales, en el mismo orden, con `pivot.position`, `pivot.service_name`, `pivot.duration_minutes` y `pivot.price_cents`. Sirve para preseleccionar en formularios; no se usa para mostrar la cita.
- `Appointment::durationMinutes(): int`: la duración total.
- `Appointment->services_label` (string).
- `AppointmentService::snapshotOf(Service $service, int $position): array`: la copia congelada.
- `App\Booking\ServiceList::ordered(iterable $services): Collection<int, Service>`: valida de 1 a `MAX_SERVICES` servicios distintos y los ordena por `sort_order`, nombre e `id`. Lanza `InvalidArgumentException` si la lista no es válida. `ServiceList::label(iterable $names): string`.
- `CreateAppointment::handle(Collection $services, CarbonImmutable $startsAt, array $customer, AppointmentSource $source, bool $applyPublicRules, ?CarbonImmutable $now = null): Appointment`. Es la misma firma que antes, con una `Collection<int, Service>` en lugar de un `Service`.
- `RescheduleAppointment::handle(Appointment $appointment, Collection $services, CarbonImmutable $startsAt, array $customer, bool $ignoreHoursAndCapacity, ?CarbonImmutable $now = null, ?int $expectedVersion = null): RescheduleOutcome`. `RescheduleOutcome::$rescheduled` es verdadero si cambian la hora **o la lista de servicios**.
- `AvailabilityCalculator` no cambia: se le pasa la suma de duraciones.
- Fábrica: `Appointment::factory()` sigue creando un servicio por defecto, con el nombre de `services_label` y la duración de la cita. `Appointment::factory()->withServices(Service ...$services)` reserva esos servicios en ese orden y, si no se indica `ends_at`, dura la suma. También hay `AppointmentService::factory()`.

## Implementación

- **Crear.** `ServiceList::ordered()` valida y ordena fuera de la transacción. Dentro, el bloqueo de `booking_settings` sigue siendo la primera consulta. La disponibilidad se comprueba con la suma, y la cita y sus `appointment_services` se insertan en la misma transacción, así que nunca existe una cita sin servicios. PRF-035 y el límite de citas futuras siguen contando citas (PRF-131).
- **Mover.**
  - Después del bloqueo, relee la cita y sus servicios.
  - **Misma lista** (por `id` y en orden): conserva los servicios, `services_label` y la duración tal como se reservaron. Es la regla anterior de «conservar el servicio».
  - **Lista distinta:** cada servicio que ya tenía conserva su copia congelada (PRF-126) y cada uno nuevo toma los datos actuales. La duración es la suma.
  - La regla de no volver a comprobar la disponibilidad (L1 de T019) vale si no cambian ni la hora ni la duración total.
  - Los servicios se sustituyen (`delete` más `insert`) **después** de que el `UPDATE` condicional `WHERE status = confirmed` haya encontrado la fila, dentro de la misma transacción. Una cancelación concurrente hace que el `UPDATE` no encuentre nada y el movimiento se rechaza antes de tocar los servicios. Dos movimientos se serializan por el bloqueo, y el segundo lee y sustituye lo que dejó el primero. Si algo falla a mitad, el *rollback* lo deshace todo.
- **Adaptación mínima.** Los formularios siguen enviando un solo `service_id`:
  - `BookingController@store` y `AppointmentController@store`/`update` pasan `collect([$service])`;
  - en `UpdateAdminAppointmentRequest` y en el desplegable de `edit`, los servicios propios de la cita pueden estar desactivados;
  - `edit.blade.php` preselecciona el primer servicio de la cita;
  - las vistas (agenda, rejilla, correos y `/cita`) leen `services_label`.

  Aviso para T048: si se guarda desde el formulario actual una cita con varios servicios, se quedaría con el primero. Ahora no hay ninguna interfaz que cree citas con varios servicios.

## Plan de pruebas

`tests/Feature/Booking/MultiServiceAppointmentTest.php` (nuevo, 22 casos):

- **Crear:**
  - orden del salón y suma;
  - datos congelados;
  - disponibilidad con la duración total;
  - una sola plaza con capacidad 2;
  - hasta 5 servicios;
  - lista vacía, 6 servicios o un servicio repetido, sin crear nada;
  - límite de citas futuras con una cita de 3 servicios;
  - el bloqueo es la primera consulta y la cita se inserta antes que sus servicios;
  - un fallo al insertar los servicios no deja cita.
- **Mover:**
  - conservar los datos congelados de los servicios que ya tenía y tomar los actuales de los nuevos;
  - quitar un servicio acorta la cita;
  - con la misma lista, no se tocan los servicios aunque se hayan editado;
  - disponibilidad de la lista nueva;
  - lista no válida;
  - orden de las consultas: bloqueo, `UPDATE`, `delete` e `insert`;
  - un movimiento después de una cancelación concurrente no toca los servicios;
  - dos movimientos concurrentes dejan exactamente los servicios del último;
  - un fallo al reescribir los servicios deshace todo el movimiento.
- **Fábrica** y `ServiceList`.

Los tests existentes solo se han adaptado al modelo nuevo:
- en las fábricas, `service_id` pasa a `withServices()` y `service_name` a `services_label`;
- las Actions reciben `collect([...])`.

Mutaciones comprobadas (y deshechas):
- si no se conservan los datos congelados al mover, falla 1 test;
- si se sustituyen los servicios antes del `UPDATE` condicional, falla 1 test;
- si la duración es la del primer servicio en lugar de la suma, fallan 4.

## Base local

Antes, la conexión del contenedor `app` apuntaba a `mysql`/`peluqueriajenver` y el lote 3 contenía solo `create_appointments_table` y `create_schedule_blocks_table`. Atención: `migrate:rollback --step=N` deshace **N migraciones**, no N lotes. `--step=1` solo deshizo `schedule_blocks`. Se completó con `migrate:rollback --step=3`, comprobado antes con `--pretend` (`schedule_blocks`, `appointment_services` y `appointments`), y `migrate`. Se perdieron las 3 citas de prueba, como estaba aprobado. Se conservan los 4 servicios, los 2 usuarios, el horario y los ajustes.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (565 tests en verde) · `vendor/bin/pint --test` sobre los archivos tocados (en verde).
