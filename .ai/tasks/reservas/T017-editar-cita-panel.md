# T017 — Mover y editar una cita confirmada desde el panel

- **Tipo:** ARCHITECTURAL
- **Puntos de referencia:** PRF-077, PRF-078, PRF-079, PRF-080, PRF-081, PRF-082, PRF-083, PRF-084, PRF-085, PRF-086 (refuerza PRF-025, PRF-026, PRF-039, PRF-046, PRF-054)
- **Depende de:** T004 (motor de disponibilidad), T006 (agenda del panel), T010 (notificaciones), T014 (bloqueo de concurrencia ya documentado en `CreateAppointment`)
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium` (subir a `high` si el mecanismo de «guardar igualmente» exige una segunda vuelta de validación poco trivial)
- **Motivo:** cambia el dominio central de reservas (motor de disponibilidad, bloqueo de concurrencia, notificaciones), con una decisión de producto no trivial (permitir sobrepasar capacidad u horario con confirmación explícita).
- **Estado:** done (pendiente de la comprobación manual en el navegador)
- **PR / rama:** `feature/booking-admin-tweaks`, sobre T015, T016 y T018 (implementada por un agente Opus 5.5 aparte, 2026-10-05).

## Implementación

Decisiones del usuario aplicadas: se mueve la misma fila (mismo `id` y mismo token); el correo de cambio es *best-effort* y sin reintento, y el panel avisa si falla; «Guardar igualmente» solo existe al mover una cita, nunca al crearla, y no salta nunca la comprobación de hora pasada; los datos de la clienta se editan con las reglas del alta; solo se mueven citas confirmadas y futuras; no hay historial ni texto en `notes`.

Decisiones de implementación:

- **Concurrencia.** `App\Actions\RescheduleAppointment` replica `CreateAppointment`: la transacción empieza con `lockForUpdate()` sobre `booking_settings` (primera consulta, con test), y después relee la cita, comprueba que sigue confirmada y futura y vuelve a calcular la disponibilidad. La escritura es un único `UPDATE … WHERE id = ? AND status = confirmed`, como en `CancelAppointment`: una cancelación concurrente (que no toma el bloqueo) hace que el movimiento no toque ninguna fila y se rechace; dos movimientos concurrentes se serializan por el bloqueo y el último deja todos sus valores, nunca una mezcla.
- **Motor.** `AvailabilityCalculator::isAvailable()` gana `?int $excludeAppointmentId`, que saca la propia cita de la ocupación. No se ha añadido un parámetro para saltar horario/capacidad (el plan lo proponía «por ejemplo»): con «Guardar igualmente» la Action simplemente no llama al motor, y la comprobación de hora pasada vive en la Action, antes y siempre (`StartTimeInPastException`, sin opción de forzar). `store()` no cambia.
- **«Guardar igualmente».** El botón lleva como valor el servicio, la fecha y la hora para las que se mostró el aviso (`UpdateAdminAppointmentRequest::slotKey()`); si la persona cambia cualquiera de los tres después del aviso, la nueva elección se vuelve a comprobar y a avisar. El botón va después de «Guardar cambios» en el formulario, para que pulsar Intro en un campo nunca fuerce el guardado. El aviso es `role="alert"`, recibe el foco (`tabindex="-1"` + `autofocus`) y los campos de servicio, fecha y hora lo enlazan con `aria-describedby`.
- **Servicio.** Si no cambia, la cita conserva el nombre y la duración con los que se reservó (como cuando se edita el servicio después); si cambia, toma el nombre y la duración actuales del nuevo. La cita puede conservar su propio servicio aunque se haya desactivado; cualquier otro tiene que estar activo.
- **Correo.** Solo se envía si cambian el día, la hora o el servicio y la cita tiene email (al email guardado con el cambio). Editar solo los datos de la clienta no envía nada.
- **Cita no movible.** `edit` y `update` sobre una cita cancelada o ya empezada redirigen a la agenda de ese día con un aviso y no guardan nada.

## Objetivo

Una persona del salón puede, desde la agenda del panel, mover una cita confirmada que todavía no ha empezado a otro día, otra hora y, si hace falta, otro servicio, y editar a la vez los datos de la clienta (nombre, teléfono, email, observaciones). Si el hueco nuevo no tiene capacidad o cae fuera de horario, el panel avisa pero permite guardar igualmente tras una confirmación explícita. La clienta recibe un correo con la nueva fecha/hora y el mismo enlace que ya tenía. No se guarda ningún historial del cambio.

## Contexto

- **Acción de referencia — creación:** `app/Actions/CreateAppointment.php`. Patrón a replicar: `DB::transaction()` cuya primera lectura es `BookingSetting::query()->lockForUpdate()->orderBy('id')->firstOrFail()` (el docblock de `handle()`, líneas 24-45, explica por qué el bloqueo **debe** ser la primera lectura bajo MySQL/InnoDB REPEATABLE READ — léelo antes de escribir la nueva Action, el mismo razonamiento aplica aquí).
- **Acción de referencia — cancelación (actualización condicional):** `app/Actions/CancelAppointment.php`. Usa `Appointment::query()->whereKey($id)->where('status', Confirmed)->update([...]) === 1` para que una ejecución concurrente nunca produzca un resultado mezclado (PRF-086 reutiliza esta idea).
- **Motor de disponibilidad:** `app/Booking/AvailabilityCalculator.php`. `isAvailable(int $durationMinutes, CarbonImmutable $start, CarbonImmutable $now, bool $applyPublicRules): bool` (líneas 83-112) **no tiene** forma de excluir una cita de la ocupación. Con `applyPublicRules = false` (el que usa el panel), el método igual exige que el hueco caiga dentro de un tramo de horario (`$range === null` → `false`, líneas 93-98) y que `hasCapacity()` lo permita (línea 111) — es decir, **hoy el panel ya no puede crear ni, si se reutiliza tal cual, mover una cita fuera de horario o sin plaza**. PRF-079/PRF-080 piden avisar y permitir forzarlo, así que `isAvailable()` necesita:
  - un parámetro para excluir la propia cita de la ocupación (`loadOccupation()`/`hasCapacity()`, líneas 170-197), y
  - una forma de que la llamada desde el panel, solo al mover una cita existente y solo tras la confirmación explícita, se salte la comprobación de rango/capacidad sin dejar de comprobar que la hora no es pasada (`$start->lt($now)`, línea 90, es una comprobación incondicional que **nunca** debe poder saltarse).
  - **No** cambies el comportamiento de `AppointmentController::store()` (creación de citas nuevas desde el panel): el «guardar igualmente» es solo para mover una cita ya existente (PRF-080). Si `isAvailable()` gana un parámetro de «forzar», que `store()` siga llamándolo siempre en `false`.
- **Controlador y rutas:** `app/Http/Controllers/Admin/AppointmentController.php` tiene `create()`/`store()`/`cancel()`, sin `edit()`/`update()`. Rutas actuales en `routes/web.php:76-78` (`admin.appointments.create/store/cancel`, dentro del grupo `auth`). Añade `GET admin/citas/{appointment}/editar` → `edit` (`admin.appointments.edit`) y `PUT admin/citas/{appointment}` → `update` (`admin.appointments.update`).
- **Validación de alta:** `app/Http/Requests/Admin/StoreAdminAppointmentRequest.php` (servicio activo, fecha, hora múltiplo de 5, nombre 2-100, teléfono con la regla `App\Rules\PhoneNumber`, email opcional, notas hasta 500). PRF-081 pide las mismas reglas para editar.
- **Vista de alta (plantilla a reutilizar):** `resources/views/admin/appointments/create.blade.php`.
- **Agenda:** `resources/views/admin/agenda/index.blade.php`, líneas 44-62: cada cita confirmada solo tiene el botón «Cancelar cita» (formulario `POST admin.appointments.cancel`). Añade un enlace «Editar» → `admin.appointments.edit` junto a él, visible solo si `$appointment->isConfirmed()` y `$appointment->starts_at->isFuture()` (PRF-084).
- **Notificaciones:** `app/Booking/AppointmentNotifier.php` tiene `sendCreationNotices()` (reintentable vía `customer_notified_at`/`salon_notified_at` y el comando `appointments:notify-pending`) y `sendCancellationNotices()` (best-effort, sin reintento, con `report()` si falla — líneas 38-45). PRF-082/PRF-083 piden el mismo patrón best-effort que la cancelación: añade `sendRescheduleNotice(Appointment $appointment)`.
- **Mailable y vista a crear:** sigue el patrón de `app/Mail/AppointmentConfirmedMail.php` + `resources/views/mail/appointment-confirmed.blade.php` (asunto con el nuevo día/hora, mismo `appointmentUrl = route('cita.show', $appointment->token)`, mismo tema de correo que deja T016 — coordínalo si T016 todavía no está en la rama).
- **Modelo:** `app/Models/Appointment.php`. El token no cambia nunca tras crearse (`token` no está en las reglas de validación ni se regenera). PRF-085 prohíbe escribir nada en `notes` como rastro del cambio: no añadas texto automático a ese campo.
- **Tests existentes relevantes:** `tests/Feature/Admin/AdminAppointmentTest.php` (alta y cancelación desde el panel), `tests/Feature/Booking/AvailabilityCalculatorTest.php`, `tests/Feature/Booking/CreateAppointmentTest.php` (incluye el test de que el bloqueo es la primera consulta de la transacción — replica ese patrón para la nueva Action), `tests/Feature/Admin/AgendaTest.php`.

## Plan

1. **`AvailabilityCalculator`:** añade `?int $excludeAppointmentId = null` a `isAvailable()` (y pásalo a través de `loadOccupation()`/`hasCapacity()` filtrando esa cita de `context['appointments']`). Añade un modo que permita saltar la comprobación de rango/capacidad (por ejemplo, un parámetro `bool $skipCapacityAndHoursCheck = false`) manteniendo siempre la comprobación de que `$start` no es pasado. Documenta en el docblock por qué existe cada parámetro nuevo, siguiendo el estilo ya usado en la clase.
2. **Nueva Action** `app/Actions/RescheduleAppointment.php`: mismo patrón de `DB::transaction()` + `lockForUpdate()` primero que `CreateAppointment`. Dentro:
   - comprueba que la cita sigue `confirmed` y que su `starts_at` (el actual, antes de mover) sigue en el futuro — si no, no hace nada y lo señala (PRF-084);
   - si no se ha confirmado «guardar igualmente», llama a `isAvailable()` con `excludeAppointmentId` y sin saltar las comprobaciones; si no está disponible, no guarda nada y lo señala (PRF-079);
   - si se ha confirmado «guardar igualmente», se salta la comprobación de rango/capacidad (nunca la de hora pasada) y guarda;
   - actualización condicional (`WHERE id = ? AND status = 'confirmed'`, como `CancelAppointment`) de `service_id`, `service_name`, `starts_at`, `ends_at` y, si se editan, `customer_name`, `customer_phone`, `customer_email`, `notes` — nunca el `token` (PRF-086, concurrencia).
3. **`UpdateAdminAppointmentRequest`** (`app/Http/Requests/Admin`): mismas reglas que `StoreAdminAppointmentRequest` más un campo `force` (booleano, por defecto `false`) para la confirmación de PRF-080.
4. **`AppointmentController`:** `edit(Appointment $appointment)` (404 o redirección si no es editable, ver PRF-084) y `update(UpdateAdminAppointmentRequest $request, Appointment $appointment, RescheduleAppointment $action, AppointmentNotifier $notifier)`. Si la Action señala que el hueco no está disponible y no se pidió forzar, vuelve al formulario con el aviso de PRF-079 (mantiene los datos escritos). Si guarda, llama a `$notifier->sendRescheduleNotice($appointment)` y redirige a la agenda del nuevo día con un mensaje de éxito.
5. **Rutas** en `routes/web.php`, junto a las de `appointments.*` ya existentes.
6. **Vista** `resources/views/admin/appointments/edit.blade.php` (basada en `create.blade.php`, precargada con los datos actuales) con los campos de servicio/fecha/hora/cliente y, cuando el hueco no esté disponible, el aviso con el botón «Guardar igualmente» (que reenvía el formulario con `force=1`).
7. **Enlace «Editar»** en `admin/agenda/index.blade.php`, junto a «Cancelar cita», solo si la cita es confirmada y futura.
8. **`AppointmentNotifier::sendRescheduleNotice()`** + `App\Mail\AppointmentRescheduledMail` + `resources/views/mail/appointment-rescheduled.blade.php`.

## Fuera de alcance

- Autoservicio de la clienta para mover su propia cita (sigue pospuesto, solo se descarta el autoservicio — ver «No-objetivos» de la especificación).
- Historial o auditoría de cambios de cita (PRF-085 lo prohíbe explícitamente: no hay tabla ni campo nuevo para esto).
- Cambiar el comportamiento de `AppointmentController::store()` (creación de citas nuevas) para permitir forzar capacidad/horario: eso no lo pide la especificación y queda fuera.
- Elegir peluquera o asignar la cita movida a una persona concreta (no-objetivo general de la especificación).

## Criterios de aceptación

- Mover una cita confirmada futura a otro día/hora/servicio con hueco disponible la actualiza sin pedir confirmación adicional (PRF-077, PRF-078).
- Mover una cita a un hueco que solo está libre porque es el que ella misma ocupaba (por ejemplo, adelantarla 15 minutos con el resto de la agenda igual) se permite: no cuenta contra sí misma (PRF-078).
- Elegir un hueco sin capacidad o fuera de horario muestra el aviso y no guarda nada hasta confirmar «Guardar igualmente»; al confirmarlo, se guarda (PRF-079, PRF-080).
- Cambiar a la vez el teléfono o el email de la clienta junto con la hora aplica las mismas reglas de validación que el alta, y ambos cambios se guardan juntos (PRF-081).
- Mover una cita con email envía un correo con la nueva fecha/hora y la misma URL `/cita/{token}` que ya tenía la clienta (PRF-082).
- Si el envío de ese correo falla (simulando una excepción de transporte, como ya hace `AppointmentNotificationsTest`), el cambio de la cita queda guardado igualmente y no hay un segundo intento automático por `appointments:notify-pending` (PRF-083).
- Intentar editar una cita cancelada, o una cuya hora ya pasó, no lo permite: ni la vista ofrece el enlace «Editar», ni la ruta `update` guarda ningún cambio si se llama directamente (PRF-084).
- Tras mover una cita, sus observaciones y el resto de sus datos no contienen ningún texto generado automáticamente por el cambio (PRF-085).
- Dos peticiones de movimiento simultáneas sobre la misma cita (o un movimiento y una cancelación simultáneos) dejan un resultado consistente con una sola de las dos operaciones, nunca mezclado (PRF-086, mismo patrón que el test de doble cancelación de `CreateAppointmentTest`).
- La suite completa del proyecto sigue en verde y Pint no señala nada.

## Plan de pruebas

- `tests/Feature/Booking/AvailabilityCalculatorTest.php`: casos nuevos para `excludeAppointmentId` (la propia cita no cuenta contra su hueco) y para el modo que salta rango/capacidad sin saltar la comprobación de hora pasada.
- `tests/Feature/Admin/AdminAppointmentTest.php` (o un archivo nuevo `tests/Feature/Admin/RescheduleAppointmentTest.php` si crece demasiado): mover una cita con éxito; mover a un hueco sin capacidad sin forzar (rechazado, con el mensaje); mover a un hueco sin capacidad forzando (aceptado); editar los datos de la clienta a la vez; intentar editar una cita cancelada o pasada (rechazado); correo de aviso enviado con `Mail::fake()` y el mismo token en la URL; fallo de envío simulado que no deshace el cambio; verificación de que `notes` no cambia por el movimiento salvo que la persona del salón lo edite ella misma.
- Test de concurrencia (replica el patrón de `CreateAppointmentTest` para el bloqueo de `CancelAppointment`/`CreateAppointment`): dos llamadas a la Action con instancias obsoletas del mismo registro, una sola debe tener efecto.
- `tests/Feature/Admin/AgendaTest.php`: el enlace «Editar» aparece solo en citas confirmadas y futuras.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (la suite completa, no solo los tests nuevos) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent`. Revisión manual en `localhost:8082/admin/agenda`: crear una cita de prueba, moverla a un hueco válido y a uno sin capacidad (con y sin forzar), y comprobar el correo en `storage/logs/laravel.log` (mailer `log` en local).

Resultado (2026-10-05): suite completa en verde (304 tests; eran 258) y Pint sin cambios. Tests nuevos: `tests/Feature/Booking/RescheduleAppointmentTest.php` (Action y concurrencia), `tests/Feature/Admin/AdminRescheduleAppointmentTest.php` (panel y correo), y casos añadidos en `AvailabilityCalculatorTest`, `AgendaTest`, `MailBrandingTest` y `MailContentEscapingTest`. El correo se ha renderizado en `storage/app/mail-preview/5-appointment-rescheduled.html` (no comprometido). Queda la revisión manual en el navegador.

## Riesgos

- Es el cambio más delicado del lote: toca la misma pieza de concurrencia que ya protege contra el overbooking (`CreateAppointment`, hallazgo L1 de la revisión). Un orden de lecturas equivocado en la nueva Action reabriría ese riesgo sin que falle ningún test si no se replica el test de «el bloqueo es la primera consulta».
- Permitir «guardar igualmente» con un hueco sin capacidad es una ampliación deliberada de una invariante que hoy se cumple siempre (`confirmadas ≤ capacidad`); confirma con el equipo cualquier otro sitio del código que dé por hecho que nunca se supera (por ejemplo, informes o exportaciones futuras).
