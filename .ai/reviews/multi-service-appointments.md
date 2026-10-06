# Revisión independiente: varios servicios por cita (T046–T049)

- **Alcance:** `git diff feature/agenda-service-filter...feature/multi-service-appointments` (7 commits: `e11f711` especificación, `8ca72df` T046, `590dea3` T047, `121ed38` T048, `faf6b13` T049, `f5c6de5` cierre, `97f3c5c` datos legales), contrastado con `.ai/specs/reservas.md` (PRF-125 a PRF-132 y los PRF ajustados, CA-16) y `.ai/tasks/reservas/T046`–`T050`.
- **Skills:** `review` y `architecture-review` (`.claude/skills/`).
- **Fecha:** 2026-10-06.
- **Revisor:** Claude Opus 5.5, en un contexto limpio. No ha participado en la implementación.
- **Tests:** `docker compose exec -T -u www-data app php artisan test --compact` → reproducido: **609 pasados (2329 aserciones)**. Único aviso: Pest no puede escribir su caché de resultados (permisos del volumen), sin efecto, igual que en revisiones anteriores.
- **Build:** `docker compose exec -T node npm run build` → reproducido: compila sin errores.
- **Pint:** `vendor/bin/pint --test` sobre los `.php` del diff (sin vistas) → `pass`.
- **Navegador:** no lo he comprobado yo. Ver «Para comprobar en el navegador» al final.
- **Sin datos creados:** la única comprobación en vivo fue un `tinker` con `Validator::make()` sobre un array en memoria (M2), sin tocar MySQL.

## Resolución

Todos los hallazgos se resolvieron en T051 (`.ai/tasks/reservas/T051-resolver-revision-varios-servicios.md`), en `feature/multi-service-appointments`. Suite: 631 tests en verde. `pint --test` pasa en los archivos tocados y `npm run build` compila. Siguen pendientes las comprobaciones en el navegador de la lista del final.

## Resumen

| Severidad | Nº |
| --- | --- |
| Critical | 0 |
| High | 0 |
| Medium | 2 |
| Low | 6 |

- M1. Ningún test ejercita el `UPDATE` condicional del movimiento con listas: la «cancelación concurrente» se hace antes del movimiento y la para la comprobación previa `isConfirmed()`. Quitar `where('status', Confirmed)` del `UPDATE` no haría fallar ningún test.
- M2. Un `POST /reservas` manipulado con `service_ids` de claves no consecutivas (`service_ids[a]=3` o `service_ids[5]=3`) da un 500, y los errores de validación de `service_ids` (repetido, más de 5) no se muestran en ningún sitio de la página.
- L1. En `GET /reservas`, un servicio repetido (o el mismo id 6 veces) y los valores no numéricos se descartan en silencio y se aceptan, en contra de PRF-127 y T047.
- L2. El orden del salón se calcula en PHP (comparación binaria) y en la base de datos (colación sin distinción de mayúsculas ni acentos): con el mismo «Orden», la cabecera del paso 2 y la cita guardada pueden mostrar los servicios en orden distinto.
- L3. Si el salón cambia el «Orden» de los servicios, guardar sin cambios una cita con varios servicios se trata como un cambio de servicios: reescribe las filas y el resumen y envía a la clienta el correo «El salón ha cambiado tu cita».
- L4. En `create`/`edit` del panel, `aria-invalid`/`aria-describedby` van en un `<div>` sin rol, y el error de `service_ids.*` no tiene `id`, así que `aria-describedby` apunta a un elemento que no existe.
- L5. `AGENTS.md:382` sigue diciendo que la release añade «five tables»: ahora son seis, con `appointment_services`.
- L6. Detalles de la interfaz frente al plan: el panel no muestra la duración total en `create`/`edit` (plan de T048), y «Cambiar» en `/reservas` vuelve al paso 1 con todo desmarcado.

---

## Medium

### M1. Ningún test ejercita el `UPDATE` condicional del movimiento con listas

- **Estado:** resolved
- **Resolución:** dos tests nuevos en `MultiServiceAppointmentTest` meten el cambio **dentro** de la ventana con `duringMoveWindow()`. Es un `DB::listen` que se dispara una sola vez, justo después de que la Action relea `appointment_services` y antes del `UPDATE` condicional.
  - «a cancellation committed between the re-read and the conditional update stops the move before it touches the services»: lanza `AppointmentNotMovableException`, y la hora, el resumen y las filas quedan idénticos.
  - «a move committed by someone else inside the window is overwritten whole, never mixed»: queda exactamente la lista del movimiento, con 2 filas y sin la de «Tinte».
  - Mutaciones comprobadas y deshechas: quitar `where('status', Confirmed)` del `UPDATE` hace fallar el primero; borrar solo las filas releídas (`whereIn('id', …)`) en lugar de todas las de la cita hace fallar el segundo (mezcla de filas, que choca con `unique(appointment_id, position)`).
  - SQLite tiene una sola conexión, así que el cambio simulado corre dentro de la propia transacción y se deshace con ella si la Action la revierte. Está explicado en el ayudante.
- **Evidencia:**
  - El orden de `RescheduleAppointment::handle()` es correcto (`app/Actions/RescheduleAppointment.php:123-134`, `:180-192`): primero el bloqueo, después la relectura y `isConfirmed()`, el `UPDATE ... WHERE status = confirmed` y, solo si encuentra la fila, `delete` más `createMany` de los servicios, todo en la misma transacción.
  - Pero `tests/Feature/Booking/MultiServiceAppointmentTest.php:243-254` («a move with new services sent after a concurrent cancellation leaves the services untouched») cancela **antes** de llamar a la Action. Dentro de la transacción, `Appointment::find()` ya ve la cita cancelada y la rechaza en `:127` (`! $current->isConfirmed()`), sin llegar al `UPDATE` condicional. Con `RescheduleAppointmentTest.php:170` pasa lo mismo.
  - El test de orden de consultas (`MultiServiceAppointmentTest.php:232-241`) comprueba que el `UPDATE` va antes del `delete`, pero no que un `UPDATE` que no encuentra filas deje los servicios intactos.
  - La ventana real es la de una cancelación (que no toma el bloqueo de `booking_settings`) que confirma entre la relectura y el `UPDATE`. Es justo lo que la petición pedía comprobar con listas.
- **Impacto:** el código actual es correcto, pero la garantía principal de concurrencia con listas («ni huérfanos ni mezclas ante una cancelación concurrente») no está protegida contra regresiones. Si alguien quita el `where('status', …)`, o mueve la sustitución de servicios antes del `UPDATE`, seguirían pasando los tests, salvo el de orden en el segundo caso.
- **Recomendación:** añadir un test que meta la cancelación **dentro** de la ventana. Por ejemplo, un `DB::listen` que, al ver la consulta `select … from "appointment_services"` de la Action, ejecute un `update appointments set status = 'cancelled'` en la misma conexión. Después, comprobar que se lanza `AppointmentNotMovableException`, que la cita sigue cancelada con su hora y su `services_label`, y que `appointment_services` conserva exactamente las filas anteriores. Verificar que falla al quitar el `where('status', …)` (mutación) y deshacer el cambio.

### M2. Un `service_ids` con claves que no forman una lista da un 500 en la reserva pública, y sus errores de validación no se muestran

- **Estado:** resolved
- **Resolución:** `StoreBookingRequest` valida `service_ids` con la regla `list`, así que `service_ids[a]=3` y `service_ids[5]=3` son un error de validación y no un 500. Por si acaso, el controlador usa `array_values()` y también `servicioRouteParam()`.
  - `StoreBookingRequest::failedValidation()` devuelve cualquier error de `service_ids`/`service_ids.*` al **paso 1**, con los ids válidos marcados (`cambiar=1`) y el aviso «Esa selección de servicios no es válida» (`id="services-error"`, `role="alert"`). El aviso está enlazado al `<fieldset>` con `aria-describedby`, y cada casilla lleva `aria-invalid`.
  - Tests en `MultiServiceBookingTest`: «a submitted service list whose keys are not a list is refused without a server error» (2 casos, 302) y «a refused list of services sends the customer back to step 1 with a visible notice tied to the checkboxes» (repetido y claves que no son lista, con `followingRedirects`).
- **Evidencia:**
  - `StoreBookingRequest.php:47-48` valida `service_ids` con `array`, sin lista de claves permitidas. Un `service_ids[a]=3` o `service_ids[5]=3` pasa la validación con sus claves (reproducido con `tinker`: `validated()` devuelve `['service_ids' => ['a' => '1']]`).
  - `BookingController::store()` (`app/Http/Controllers/BookingController.php:146-149`) pasa ese array a `servicioRouteParam()`, que con un solo elemento lee `$ids[0]` (`:140`). Eso da `Undefined array key 0`: un *warning* que Laravel convierte en `ErrorException` y en un **500**, antes de llegar a `CreateAppointment`. No se crea nada, pero la respuesta es un error de servidor.
  - Además, `reservas-form.blade.php` y `reservas.blade.php` no tienen `@error('service_ids')` ni `@error('service_ids.*')`. Un `POST` con un servicio repetido o con 6 vuelve a la página sin ningún mensaje. PRF-033 pide que una manipulación muestre «Esa hora ya no está disponible. Elige otra.». Los tests (`MultiServiceBookingTest.php:103-134`) solo comprueban `assertSessionHasErrors`, no lo que ve la clienta.
- **Impacto:** cualquiera puede provocar 500 en un endpoint público con una petición trivial (ruido en los logs y en la monitorización del despliegue). Una petición manipulada que falla la validación no recibe ninguna explicación. No hay riesgo para los datos.
- **Recomendación:**
  - Normalizar con `array_values()` antes de usar el array: en `servicioRouteParam()` o al leer `validated('service_ids')`. También se puede validar `'service_ids' => ['required', 'list', …]` (Laravel ≥ 11), que rechaza claves no consecutivas.
  - Mostrar un mensaje para los errores de `service_ids`/`service_ids.*`. Por ejemplo, convertirlos al de PRF-033 con `messages()` o con un `@error` junto a `time`.
  - Añadir tests: un `service_ids[5]=…` sin 500, y el texto visible tras un `POST` con un servicio repetido.

---

## Low

### L1. `GET /reservas` acepta en silencio servicios repetidos y valores no numéricos

- **Estado:** resolved (normalizar, documentado en PRF-127)
- **Resolución:** en el `GET`, un servicio repetido o un valor que no es un id se **quita** (`requestedServiceIds()` devuelve también `adjusted`). La página sigue en el paso 2 con un aviso discreto, «Hemos quitado de tu selección los servicios repetidos o no válidos.» (`role="status"`), y nunca se rompe.
  - Más de 5 servicios distintos, o uno que no existe o no es reservable, sigue rechazando la elección entera.
  - PRF-127 tiene una aclaración con este criterio.
  - Tests: «repeated or non-numeric services in the page address are tidied up with a discreet notice, never a broken page» (repetido, el mismo 6 veces y `abc`) y «a clean selection shows no "tidied up" notice».
- **Evidencia:** `BookingController::requestedServiceIds()` (`app/Http/Controllers/BookingController.php:99-108`) aplica `array_filter(..., 'is_numeric')` y `array_unique()` **antes** de contar y validar en `selectedServices()` (`:121-130`). Por eso, `?servicio[]=3&servicio[]=3`, `?servicio[]=3&servicio[]=abc` o el mismo id 6 veces pasan al paso 2 con un solo servicio. PRF-127 dice: «Una elección con un servicio repetido, más de 5 o ninguno **no debe** aceptarse». T047 dice: «una selección repetida […] se rechaza entera». El docblock de `selectedServices()` habla de rechazar la selección entera, pero los repetidos no llegan a ella. Ningún test cubre el repetido ni el no numérico en el `GET` (solo `MultiServiceBookingTest.php:103` en el `POST`).
- **Impacto:** poco práctico, porque las casillas no pueden repetir valores y el `POST` sí rechaza los repetidos. Aun así, es una divergencia con el texto normativo, y la matriz marca PRF-127 como `covered`.
- **Recomendación:** comparar el recuento antes y después de filtrar y de quitar duplicados. Si cambia, tratar la selección como no válida (`invalidSelection`). Añadir tests con `?servicio[]=3&servicio[]=3` y `?servicio[]=3&servicio[]=x`. La otra opción es que el usuario decida suavizar PRF-127 para el `GET` y se anote en la spec.

### L2. El orden del salón se calcula de dos formas distintas

- **Estado:** resolved
- **Resolución:** hay un único criterio, `ServiceList::sort()`: `sort_order` y, a igualdad, `id` (el orden de alta). **Nunca el nombre**, porque PHP y MySQL lo comparan distinto. Se usa en todos los sitios que ordenan los servicios de una cita:
  - guardar (`ordered()`);
  - la cabecera del paso 2 de `/reservas` (`selectedServices()` y `ServiceList::label()`);
  - la selección de «Cabe» de la agenda (`serviciosFromQuery()`).

  Los listados del catálogo (`scopeOrdered`, PRF-012) siguen por nombre, solo para mostrar. PRF-125 y T046 están actualizados.
  - Tests: «services with the same "Orden" go in the order they were created, never by name» (Action) y «step 2 and the saved booking put services with the same "Orden" in the same order» (web, con «Corte X»/«arreglo X»).
  - Se han actualizado las expectativas de 4 tests que dependían del desempate por nombre (`MultiServiceAdminTest` ×2 y `AgendaServiceFilterTest` ×2).
- **Evidencia:**
  - `ServiceList::ordered()` (`app/Booking/ServiceList.php:38`) ordena en PHP con `sortBy([['sort_order','asc'],['name','asc'],['id','asc']])`. Para cadenas es una comparación binaria, que distingue mayúsculas y minúsculas y pone los acentos después de la `z`.
  - `Service::scopeOrdered()` (`app/Models/Service.php:35-38`) ordena en MySQL con la colación de la tabla (`utf8mb4_unicode_ci`, que no distingue mayúsculas ni acentos).
  - `/reservas` muestra la cabecera del paso 2 en el orden de la base de datos (`BookingController.php:127`, sobre `$services` ya ordenados), pero `CreateAppointment` guarda las posiciones y `services_label` en el orden de PHP.
  - Con el «Orden» por defecto (0) en todos los servicios, el desempate por nombre es lo habitual. Por ejemplo, «arreglo de barba» y «Corte» se muestran como «arreglo + Corte» y se guardan como «Corte + arreglo». «Ácido…» va antes de «Barba» en MySQL y después en PHP.
- **Impacto:** el orden «del salón» (PRF-125) depende de dónde se mire: el paso 2, la cita guardada, los correos y las casillas del panel. En SQLite (tests) la colación es binaria, así que los tests no lo detectan.
- **Recomendación:** una sola fuente de orden. Por ejemplo, que `ServiceList::ordered()` ordene con `Str::lower`/`Collator` (`es_ES`), o que las Actions reordenen con una consulta `whereIn(...)->ordered()` (una consulta, fuera de la transacción o después del bloqueo). Añadir un test con dos servicios del mismo «Orden» y nombres en mayúscula y minúscula.

### L3. Reordenar el catálogo hace que guardar sin cambios cuente como cambio de servicios

- **Estado:** resolved
- **Resolución:** `RescheduleAppointment` compara la lista actual y la nueva **como conjuntos** de ids (ordenados numéricamente). El mismo conjunto en otro orden conserva las filas, las posiciones, `services_label` y la duración, y `rescheduled` es falso, así que no hay correo. Tests: «the same services in a different order are not a change: rows, summary and order are kept» (Action; mutación: comparar en orden hace fallar el test) y «saving the same services after the catalogue was reordered sends no email and changes nothing» (panel, con `Mail::fake()`).
- **Evidencia:** `RescheduleAppointment.php:139-141` compara como **listas ordenadas** los `service_id` actuales (por `position`) y los nuevos (ordenados por el «Orden» **actual** del catálogo). Si el salón cambia el «Orden» de los servicios después de reservar, el mismo conjunto llega en otro orden. Entonces `keepsServices` vale `false`, se reescriben las filas (con los datos congelados, eso sí), cambia `services_label` y `rescheduled` vale `true` (`:198`). `AppointmentController::update()` (`:276-288`) envía entonces a la clienta el correo «El salón ha cambiado tu cita», aunque solo se haya corregido el teléfono.
- **Impacto:** un correo de cambio que no corresponde a ningún cambio de hora ni de servicios, y un cambio del resumen que la clienta no ha pedido. Es poco frecuente, pero confuso.
- **Recomendación:** decidir `keepsServices` comparando **conjuntos** (ids ordenados numéricamente). Si el conjunto no cambia, conservar el orden y las filas actuales. Añadir un test: reservar [A, B], cambiar el «Orden» para que B vaya antes que A, guardar el mismo conjunto y comprobar que `rescheduled` es falso y que las filas no cambian.

### L4. Accesibilidad de los errores de servicios en `create`/`edit` del panel

- **Estado:** resolved
- **Resolución:** en `create` y en `edit`:
  - el `<fieldset id="service_ids">` (con `<legend>`) lleva `aria-describedby`: `service_ids-error` y, en `edit`, también `slot-warning-text`;
  - cada casilla lleva `aria-invalid="true"` si hay error;
  - un único `<p id="service_ids-error">` muestra `$errors->first('service_ids') ?: $errors->first('service_ids.*')`, así que la referencia nunca queda colgando.

  La reserva pública hace lo mismo con `services-error`. Test: «a refused list of services is announced on the checkboxes and points to one message that exists» (repetido, inactivo y ninguno).
- **Evidencia:**
  - `resources/views/admin/appointments/create.blade.php:39` y `edit.blade.php:47` ponen `aria-invalid`/`aria-describedby` en un `<div id="service_ids">` sin rol. `aria-invalid` no se expone en un `div` genérico, y los lectores de pantalla no lo anuncian al llegar a las casillas.
  - El error de elemento (`@error('service_ids.*')`, en `create.blade.php:48` y `edit.blade.php:56`) se muestra en un `<p>` **sin `id`**. Pero `$fieldAria` también añade `aria-describedby="service_ids-error"` cuando solo hay errores de `service_ids.*`. Ese `id` solo existe en el `<p>` de `@error('service_ids')` (`:47`/`:55`), así que la referencia queda colgando.
  - La skill `review` exige que los errores estén enlazados con `aria-describedby` y `aria-invalid`.
- **Impacto:** con un servicio repetido, inactivo o con más de 5, el lector de pantalla no anuncia el error en las casillas.
- **Recomendación:**
  - Poner `aria-describedby` en el `<fieldset>` (o en cada casilla) y `aria-invalid` en cada `<input type="checkbox">`.
  - Mostrar un único `<p id="service_ids-error">` con `$errors->first('service_ids') ?: $errors->first('service_ids.*')`.
  - Ampliar el test de `aria` existente al caso `service_ids.*`.

### L5. `AGENTS.md` habla todavía de cinco tablas nuevas

- **Estado:** resolved
- **Resolución:** `AGENTS.md` habla ya de **seis** tablas, con `appointment_services`. Además deja escrito que las migraciones `2026_10_03_*` se **editaron** en la PR, y que antes de desplegar hay que comprobar con `php artisan migrate:status` en el VPS, como `deploy`, que **ninguna** figura como ejecutada. Si alguna figurase, hay que parar y preguntar.
- **Evidencia:** `AGENTS.md:382`: «the release adds five tables (`services`, `opening_hours`, `booking_settings`, `appointments`, `schedule_blocks`)». Falta `appointment_services` (`database/migrations/2026_10_03_150050_create_appointment_services_table.php`). Es la lista que se usa para comprobar `migrate:status` antes del primer despliegue.
- **Impacto:** quien despliegue y compare `migrate:status` y `SHOW TABLES` con esa lista puede tomar la sexta tabla por inesperada, o no echarla de menos.
- **Recomendación:** pasar a seis tablas y añadir `appointment_services`. Aprovechar para dejar escrita la premisa de T046 («las migraciones de reservas nunca se han ejecutado en producción, por eso se editaron»). Antes de desplegar debe confirmarse con `php artisan migrate:status` que `2026_10_03_150000_create_appointments_table` **no** figura como ejecutada. Si figurase, la migración editada no se aplicaría y faltaría `services_label`.

### L6. Detalles de la interfaz que no siguen el plan

- **Estado:** resolved
- **Resolución:**
  - **Panel:** `create` y `edit` muestran «Duración total» con el valor del servidor (también después de un error de validación) y lo actualizan en vivo con un parcial de JS *vanilla* compartido con `/reservas` (`partials/service-total-script.blade.php`, que sustituye al script en línea de la web). En `edit`, cada servicio que la cita ya tenía suma su duración **reservada** (`data-minutes`), igual que la cuenta `RescheduleAppointment`.
  - **`/reservas`:** «Cambiar» enlaza con `servicio[]` y `cambiar=1`, y el paso 1 vuelve con la elección marcada y sin aviso.
  - Tests: «the create form shows the total length…», «after a validation error the create form shows the total of what was checked», «the edit form totals the services the way the move counts them…» y «"Cambiar" goes back to step 1 with the chosen services still checked».
  - Añadido además el test de «Cabe» en Semana con varios servicios: «semana with two services marks only where their sum fits, naming both».
- **Evidencia:**
  - El plan de T048 (punto 1) pide «el total visible» en `create`/`edit`. Las vistas (`create.blade.php:31-49`, `edit.blade.php:38-57`) solo muestran la duración de cada casilla, sin total. PRF-129 no lo exige de forma explícita.
  - «Cambiar» en `/reservas` (`resources/views/pages/reservas.blade.php:106`) enlaza a `route('reservas')` sin `servicio[]`, así que la clienta vuelve al paso 1 con todas las casillas desmarcadas. Para añadir un tercer servicio tiene que volver a marcar los dos primeros. El paso 1 ya sabe preseleccionar con `checkedIds`.
- **Impacto:** solo comodidad, sobre todo en el móvil.
- **Recomendación:**
  - En el panel, añadir el total, sin JavaScript en el servidor (la duración actual de la cita en `edit`) y con el mismo JS mínimo que `/reservas` para el total en vivo.
  - En «Cambiar», enlazar al paso 1 conservando la selección. Por ejemplo, un parámetro `cambiar=1` que fuerce el paso 1 con `checkedIds` marcados. El usuario decide si merece la pena.

---

## Comprobaciones sin hallazgos

- **Bloqueo primero y misma transacción al crear.** `CreateAppointment.php:77-114`: `lockForUpdate()` sobre `booking_settings` es la primera consulta. La cita y sus `appointment_services` se insertan después, en la misma transacción. Tests: `MultiServiceAppointmentTest.php:158` (orden de consultas) y `:167` (un fallo al insertar los servicios no deja cita), reproducidos en la suite.
- **Movimiento.**
  - Bloqueo primero (`RescheduleAppointment.php:123`).
  - La versión (`updated_at`) se compara bajo el bloqueo (`:131`).
  - La sustitución de servicios va después del `UPDATE` condicional (`:180-192`).
  - Dos movimientos se serializan: el segundo relee las filas que dejó el primero (`:139`), y lo prueba `MultiServiceAppointmentTest.php:256`.
  - El *rollback* ante un fallo a mitad lo prueba `:271`.
  - El `UPDATE` de la cancelación espera al bloqueo de fila de la cita que toma el `UPDATE` del movimiento, así que no hay mezclas. Solo falta el test de la ventana (M1).
- **Datos congelados al mover.** Los servicios que se mantienen copian `service_name`, `duration_minutes` y `price_cents` de la fila anterior (`:142-148`) y los nuevos toman los actuales (`snapshotOf`). Con la misma lista no se toca nada (`:150`). Tests: `MultiServiceAppointmentTest.php:176`, `:202` y `MultiServiceAdminTest.php:67`.
- **Duración.** La suma se usa en todos los sitios:
  - la disponibilidad pública (`BookingController.php:49`, `:87`);
  - crear (`CreateAppointment.php:75`, `:98`);
  - mover (`RescheduleAppointment.php:150`, `:167`);
  - «Cabe» (`AgendaController::markServiceFit`, `$servicios->sum('duration_minutes')`).

  La regla L1 de T019 se mantiene: no se vuelve a comprobar la disponibilidad si no cambian ni la hora ni la duración total (`:151`, `:153`). `durationMinutes()` sale de `starts_at`/`ends_at`, así que respeta una cita guardada con «Guardar igualmente».
- **Servicios desactivados.** `UpdateAdminAppointmentRequest.php:35-37` admite activos **o** los que ya tiene la cita. `Rule::exists(...)->where(Closure)` envuelve la condición entre paréntesis (`id = ? AND (is_active OR id IN …)`), sin fuga. `create` solo admite activos. En el panel se aceptan servicios no reservables online, como exige PRF-046. Test: `MultiServiceAdminTest.php:88`.
- **No reservable mezclado con reservables (web).** `BookingController.php:147-160` rechaza la petición entera con el mensaje de PRF-033 y no crea nada. Test: `MultiServiceBookingTest.php:120`.
- **Máximo único.** `Appointment::MAX_SERVICES` se usa en las tres Form Requests, en `ServiceList`, en `BookingController`, en `AgendaController`, en los textos de las vistas y en `lang` (`:max`). Un `grep` no encuentra ningún `5` escrito a mano para este límite.
- **Amplificación.** Los arrays de `servicio[]` se cortan o rechazan a 5 antes de cualquier consulta. Las consultas son siempre 1 `whereIn`, y `max_input_vars` limita el tamaño de la entrada. No hay N+1 por servicio.
- **Abuso y PRF-035.** `hasTooManyUpcoming()` y la comprobación de duplicados siguen consultando `appointments`, no `appointment_services`. Test: `MultiServiceAppointmentTest.php:148`.
- **FK y esquema.**
  - `appointment_id` lleva `cascadeOnDelete` y `service_id` lleva `restrictOnDelete`. El panel no borra servicios.
  - Hay `unique(appointment_id, position)`.
  - `services_label` es `string(512)`: 5 × 100 + 4 × 3 (« + ») = 512 caracteres exactos. `services.name` es `string(100)` y la validación es `max:100` (en caracteres, y `varchar` cuenta caracteres en utf8mb4).
  - El orden de las migraciones es correcto en un `migrate` limpio en MySQL y SQLite: `services` (133413), después `appointments` (150000) y después `appointment_services` (150050). El `down` en orden inverso también es válido.
- **Referencias antiguas.** Un `grep` de `service_name`/`service_id` en `app/`, `resources/`, `database/`, `routes/` y `tests/` solo encuentra usos legítimos de `appointment_services`. No queda `$appointment->service`, `servicioFromQuery` ni `name="service_id"`. El `DatabaseSeeder` sigue vacío.
- **Correos y `/cita`.**
  - El parcial compartido (`mail/partials/appointment-services.blade.php`) se incluye en los 5 correos y solo usa `service_name` y `duration_minutes`, nunca `price_cents`.
  - `Markdown::withSecuredEncoding()` sigue activo (`AppServiceProvider.php:32`), y lo prueba `MultiServiceDisplayTest.php:84` con Markdown en el nombre.
  - `RescheduleAppointment` hace `unsetRelation('items')` antes de devolver la cita, así que el correo de cambio carga los servicios nuevos.
- **«Cabe» con varios servicios.**
  - La suma y la persistencia de `servicio[]` (escalar con uno y array con varios) se cumplen en el formulario de fecha (campos ocultos), los enlaces y Mes.
  - No se mezcla con `volver`.
  - El número de consultas es fijo en Día con 2 servicios (`AgendaServiceFilterTest.php:346`) y en Semana con 1. No hay test de Semana con varios, pero el código no cambia el número de consultas: `serviciosFromQuery` trabaja en memoria.
  - El `<details>` no necesita JavaScript.
- **Datos legales (`97f3c5c`).** Hay coherencia entre:
  - el aviso legal: titular Isabel Lechuga Valverde, nombre comercial Peluquería Jenver, NIF, domicilio, teléfono y email;
  - la privacidad: titular, NIF, email ×2 y plazo de 2 años;
  - las cookies: email;
  - la capa básica del formulario: titular y domicilio.

  No queda «Razón social», «CIF», «sociedad» ni «empresa». El único marcador pendiente es `privacidad.blade.php:64` (proveedor de correo), y lo vigila `PrivacyPolicyTest`.
- **Build y Pint** en verde (ver la cabecera).

## Para comprobar en el navegador

El coordinador ya comprobó, a 360 px, una reserva de 2 servicios (corte y barba, 25 min), `/cita` y la agenda con «Cabe». Queda por comprobar:

1. **`/reservas` sin JavaScript** (desactivado en DevTools): marcar 2 casillas y pulsar «Ver días y horas». La URL debe llevar `servicio[]` dos veces y el total debe verse en el paso 2. Pasar de mes y volver: la selección se conserva.
2. **`/reservas` con JavaScript:** el total en vivo aparece y desaparece al marcar y desmarcar. Marcar 6 casillas y enviar: aviso «Esa selección de servicios no es válida» en el paso 1, con las casillas conservadas.
3. **Error tras enviar con 2 servicios** (por ejemplo, la hora ocupada en otra pestaña): la vuelta conserva `servicio[]` y el mensaje se ve junto a las horas.
4. **Panel, `create` a 360 px:** casillas de 44 px. Enviar sin ninguna casilla, con 6 o con un servicio inactivo (manipulando el HTML) y ver el mensaje. Con lector de pantalla, comprobar si se anuncia el error (relacionado con L4).
5. **Panel, `edit` de una cita de 2 servicios:** quitar uno y añadir otro. La tarjeta y el bloque de la rejilla muestran el nuevo resumen y la duración. «Guardar igualmente» con un hueco lleno y la lista reenviada en otro orden.
6. **Panel, `edit` de una cita con un servicio después desactivado:** aparece marcado con «· inactivo» y se puede guardar.
7. **«Cabe» en Semana (escritorio y móvil) con 2 servicios:** resaltado solo donde cabe la suma. El `<summary>` muestra los nombres recortados sin desbordar a 360 px. Al tocar un hueco, «Nueva cita» llega con los dos preseleccionados. Abrir y cerrar el `<details>` con el teclado (Enter/Espacio) y comprobar que el foco va bien.
8. **Bloque estrecho de la rejilla** con un `services_label` largo (3 a 5 servicios): el texto queda recortado en una línea, y el `title` y el `aria-label` llevan la versión completa.
9. **Correos en Gmail y Outlook** (pendiente desde antes) con 2 servicios: la lista «Servicios» y la «Duración total» se leen bien.
10. **`/privacidad`, `/avisos-legales` y `/cookies`:** revisión visual de los datos de la titular.
