# Revisión: tiempos de espera dentro de los servicios (`feature/service-wait-times`)

- **Alcance:** `git diff main..HEAD`, commits `66f3005`, `5f4bbc3`, `00de167`, `49eb7df`, `e8152b8`, `e5334a7` y `a44a7fd` (37 archivos). Contrastado con PRF-151 a PRF-160, PRF-125, PRF-110 y CA-22 de `.ai/specs/reservas.md`, las tareas T062–T065 y T067 y la matriz de `.ai/tasks/reservas/index.md`.
- **Skill:** `review` (con los puntos de `architecture-review` sobre la migración y los contratos del motor).
- **Fecha:** 2026-10-07.
- **Revisor:** Claude Opus 5.5, en un contexto limpio. No ha participado en la implementación.
- **Tarea:** T066.

## Tests

`docker compose exec -T -u www-data app php artisan test --compact`: **840 passed (4771 assertions)**, 333 s, contra SQLite en memoria. No se ha tocado la base MySQL local.

**Sin comprobar en el navegador** por este revisor. M1 se deduce del HTML servido y de la validación nativa estándar de `<input type="number">`. El coordinador debe confirmarla en Chrome (ver M1).

## Estado de la resolución

Todos los hallazgos están resueltos en T068 (`feature/service-wait-times`). La resolución de cada uno va debajo de su hallazgo. Suite completa en verde tras la resolución. Pendiente: que el coordinador vuelva a comprobar M1 y L2 en el navegador.

## Resumen

| Severidad | Nº |
| --- | --- |
| Crítica | 0 |
| Alta | 0 |
| Media | 1 |
| Baja | 5 |

El motor es correcto. No se ha encontrado ninguna vía de *overbooking*:

- **Momentos revisados.** `capacityProblem()` revisa el inicio, el inicio de cada tramo activo propio y el inicio de cada tramo activo ajeno y de cada cierre dentro de `[start, end)`. Como la ocupación más la reducción solo puede subir en esos puntos, basta con revisarlos.
- **Cierres.** Un cierre total o una capacidad efectiva 0 se revisa en toda la cita, esperas incluidas. Una reducción parcial solo cuenta en los tramos activos.
- **Bordes.** Los intervalos son `[a, b)`: si uno termina justo cuando empieza otro, no se solapan.
- **Comprobación independiente.** La fuerza bruta minuto a minuto (15 semillas) y la equivalencia aleatoria de `fittingStartMinutes` con `isAvailable` (25 semillas, con perfiles con esperas) lo confirman.

Lo demás también está bien:

- **Concurrencia.** `CreateAppointment` mantiene el mismo bloqueo, y `RescheduleAppointment` compara el perfil completo (`TimeProfile::equals`) en `keepsSlot`.
- **Servicios encadenados.** `fromServices()` no puede producir esperas pegadas, porque ningún servicio termina en espera.
- **Formulario.** `ServiceRequest` y `TimeProfile::fromSteps()`/`steps()` son inversos, y ninguna entrada que pase la validación puede lanzar `InvalidArgumentException`: cada paso vale al menos 5, no hay huecos y el último paso es un trabajo.
- **Carriles.** El reparto voraz en orden de inicio nunca usa más carriles que el máximo de tramos simultáneos, así que «Sobre capacidad» por tramo es coherente con el motor.
- **Migración.** Es solo aditiva: 3 columnas JSON que admiten nulos, en un archivo nuevo, sin tocar las `2026_10_03_*`. El `down()` es simétrico.
- **`UPDATE` de `RescheduleAppointment`.** Escribe `waits` con el *query builder*, y las gramáticas MySQL y SQLite de Laravel 13 codifican los arrays en JSON en `prepareBindingsForUpdate()`, así que el dato se guarda bien en producción.
- **Nada llega a lo público.** En `/reservas` no hay `data-wait-minutes`, y el *script* del total solo se incluye en el panel. `/cita` y los 3 correos a la clienta quedan cubiertos por tests con la palabra entera «espera».

Hallazgos:

- **M1.** La validación nativa del navegador se adelanta a los mensajes sencillos de PRF-160 para «menos de 5», «más de 600» y «no múltiplo de 5».
- **L1.** El error de «hueco» o de «espera sin trabajo después» no aparece mientras haya otro error en el formulario (nombre, precio…).
- **L2.** En el móvil, el rótulo «(1/2)» se recorta (observación del coordinador).
- **L3.** En la edición de una cita, la etiqueta de cada servicio muestra la espera actual del servicio, mientras que el total usa la congelada.
- **L4.** El error del total de más de 600 minutos no está enlazado a ningún campo.
- **L5.** Un solo registro con esperas incoherentes tumba la agenda y `/reservas` con un 500.

Además, dos notas de coherencia de la documentación (sin severidad), al final.

---

## M1 · La validación nativa tapa los mensajes en lenguaje sencillo

- **Severidad:** Media
- **Estado:** resuelto (T068, decisión del usuario)
- **Resolución:**
  - `novalidate` en los dos `<form>` de servicios (`create.blade.php` y `edit.blade.php`): siempre responde el servidor, con el texto de la spec bajo el paso. Se mantienen `type="number"`, `inputmode="numeric"`, `min`, `max` y `step` por el teclado numérico y las flechas de 5 en 5, pero ya no bloquean el envío.
  - `ServiceRequest::prepareForValidation()`: un 0 (o «00») en cualquier paso salvo «Trabajo 1» cuenta como vacío, sin error. Un 0 en «Trabajo 1» sigue dando «Como mínimo, 5 minutos.».
  - Coherencia del caso «Trabajo 1 = 30, Espera 1 = 0, Trabajo 2 = 45»: la espera cuenta como vacía y queda un trabajo detrás de un hueco. El mensaje «Rellena primero la espera 1.» no tenía sentido con un 0 escrito, así que todos los huecos pasan a «Escribe los minutos de la espera 1, o deja vacíos los pasos de después.» (o «del trabajo 2», «de la espera 2»), en el paso vacío. Da las dos salidas a la vez: si había espera, escribirla; si no la había, vaciar lo de después y sumar ese tiempo al trabajo 1. Así no se adivina nada: sumar en silencio 30 + 45 en «Trabajo 1» cambiaría lo que escribió la peluquera sin que lo viera.
  - Tests:
    - `ServiceWaitTimesReviewTest`: `novalidate` en los dos formularios; el 0 como vacío en 3 casos; el 0 en «Trabajo 1» sigue siendo un error;
    - `ServiceWaitsFormTest`: el caso «30, 0, 45» y los mensajes de hueco nuevos.
  - Pendiente: comprobación del coordinador en Chrome con 0, 32 y 605.
- **Evidencia:**
  - `resources/views/admin/services/_form.blade.php:53`: cada paso es `<input type="number" … min="5" max="600" step="5">`.
  - `resources/views/admin/services/create.blade.php:8` y `edit.blade.php:8`: el `<form>` no lleva `novalidate`.
  - Con eso, el navegador bloquea el envío y enseña su propio globo antes de llegar al servidor. En Chrome en español:
    - un 0 en una espera (lo natural para decir «no hay espera»), o un 3: «El valor debe ser superior o igual a 5»;
    - un 32: «Introduce un valor válido. Los dos valores válidos más aproximados son 30 y 35»;
    - un 605: «El valor debe ser inferior o igual a 600».
  - Los mensajes de PRF-160 («Como mínimo, 5 minutos.», «Usa múltiplos de 5 minutos (5, 10, 15…).», «Como máximo, 600 minutos.») solo se ven sin JavaScript o en los tests HTTP, que no pasan por el navegador. La comprobación del coordinador en Chrome usó «espera sin trabajo después», un caso que la validación nativa no detecta, así que no lo cubre.
- **Impacto:**
  - Contradice la exigencia explícita del usuario (PRF-160, «muy simple y fácil de entender») justo en los errores más probables para una peluquera: el 0 para decir «sin espera» y los minutos no redondos.
  - El globo nativo desaparece solo, no queda junto al campo, en algunos móviles es casi invisible, y el mensaje de paso («valores válidos más aproximados») es jerga.
- **Recomendación:**
  - Añadir `novalidate` a los dos `<form>` de servicios. Así siempre responde el servidor, con el mensaje de la spec junto al paso. También se pueden quitar `min`/`max`/`step` de los pasos y dejar `inputmode="numeric"`.
  - Opcional, a decidir por el usuario: tratar un 0 en una espera como vacío, que es lo que la peluquera quiere decir, en lugar de rechazarlo con «Como mínimo, 5 minutos.».
  - Comprobar en Chrome, a 390 px, que con 0, 32 y 605 aparece el texto de la spec bajo el paso.

## L1 · Los errores de orden de los pasos esperan a que se corrija todo lo demás

- **Severidad:** Baja
- **Estado:** resuelto (T068)
- **Resolución:** `after()` solo se detiene si alguno de `STEP_FIELDS` ya tiene error, así que los errores del orden de los pasos salen a la vez que los del nombre, el precio o el orden. Test: `ServiceWaitTimesReviewTest` › «L1: …», con el nombre vacío y una espera sin trabajo después, que espera los dos errores.
- **Evidencia:** `app/Http/Requests/Admin/ServiceRequest.php:89`. `after()` termina si `$validator->errors()->isNotEmpty()`, que incluye los errores de `name`, `price` y `sort_order`, no solo los de los pasos.
- **Impacto:** si el nombre está vacío y además falta «Trabajo 2» tras una espera, primero solo se ve el error del nombre, y al corregirlo aparece un error nuevo en los tiempos. Son dos vueltas para algo que se podía decir a la vez, y es justo el tipo de fricción que PRF-160 quiere evitar.
- **Recomendación:** salir de `after()` solo si alguno de los campos de `STEP_FIELDS` tiene error, por ejemplo con `collect(self::STEP_FIELDS)->contains(fn ($f) => $validator->errors()->has($f))`. Añadir un caso al *dataset* de `ServiceWaitsFormTest` con el nombre vacío y una espera sin trabajo después que espere los dos errores.

## L2 · En el móvil, «(1/2)» se recorta y el corte en tramos no se ve

- **Severidad:** Baja
- **Estado:** resuelto (T068, decisión del usuario)
- **Resolución:** el indicador va delante del nombre: «10:00 (1/2) Ana · Coloración». Se elige así, y no «½», porque se lee igual en el móvil y en el lector de pantalla y vale también para «(2/2)». El `aria-label` y el `title` no cambian. Test: `WaitTimesAgendaTest` comprueba «(1/2) Ana» y «(2/2) Ana». Pendiente: comprobación del coordinador a 390 px.
- **Evidencia:** `resources/views/admin/agenda/_timeline-column.blade.php:101`. El texto es «HH:MM {clienta} (1/2) · servicios» en una sola línea `truncate`, y en un carril estrecho a 390 px el sufijo va después del nombre y se pierde. Lo ha observado el coordinador en Chrome.
- **Impacto:** solo visual. El `aria-label` y el `title` mantienen la información (aunque el `title` no sirve en una pantalla táctil). La marca discontinua «Espera · …» del hueco también ayuda, pero puede recortarse igual. Quien mira la agenda en el móvil ve dos bloques con el mismo nombre sin saber que son la misma cita partida, y la tarjeta de debajo («Espera: 10:30–11:15») lo aclara solo después de tocar. No se reserva nada mal: el motor no depende de esto.
- **Recomendación:** poner el indicador antes del nombre, que es la parte que nunca se recorta: «10:00 ½ Ana» o «10:00 (1/2) Ana». Otra opción es una pista no textual que sobreviva al recorte: borde inferior discontinuo en el tramo que sigue con una espera y borde superior discontinuo en el que la continúa, coherente con el borde discontinuo de la espera. Ajustar el test de «(1/2)» de `WaitTimesAgendaTest` al nuevo orden.

## L3 · En la edición de una cita, la etiqueta de cada servicio contradice el total

- **Severidad:** Baja
- **Estado:** resuelto (T068, decisión del usuario)
- **Resolución:** en `appointments/edit.blade.php`, la etiqueta de cada servicio usa `Service::formatDurationWithWait($minutesOf($service), $waitMinutesOf($service))`: la duración y la espera congeladas si la cita ya tenía ese servicio, y las actuales si no. Coincide con `data-minutes`, `data-wait-minutes` y el total. Test cambiado: «the edit form counts the waits the appointment was booked with» espera ahora «Coloración (2 h, incl. 45 min de espera)» y no «incl. 20 min».
- **Evidencia:**
  - `resources/views/admin/appointments/edit.blade.php:58` calcula `data-wait-minutes` con la copia congelada de la cita.
  - La línea 67 rotula el servicio con `duration_with_wait_label`, que lee el servicio actual.
  - `ServiceWaitsFormTest` › «the edit form counts the waits the appointment was booked with» lo fija tal cual: la etiqueta dice «Coloración (2 h, incl. 20 min de espera)» y, con solo ese servicio marcado, el total dice «2 h, incl. 45 min de espera».
- **Impacto:** con un solo servicio marcado, la pantalla da dos esperas distintas para lo mismo. Ya pasaba con la duración (`duration_label` frente a `data-minutes` congelado), pero con las esperas es más fácil que el salón las retoque y se note. Puede confundir a la peluquera.
- **Recomendación:** que la etiqueta de los servicios que la cita ya tiene use su copia congelada (`$frozenItems[$service->id]`, la duración y la espera de `Service::formatDurationWithWait`), igual que el total, y cambiar el test para que espere «incl. 45 min» en los dos sitios. Si el usuario prefiere ver el valor actual del servicio, entonces conviene indicarlo con un texto como «ahora: …».

## L4 · El error del total no está enlazado a ningún campo

- **Severidad:** Baja
- **Estado:** resuelto (T068)
- **Resolución:** con ese error, el `<fieldset>` lleva `aria-describedby="duration_minutes-error"`, y cada paso que no tiene error propio lleva `aria-invalid="true"` y `aria-describedby="duration_minutes-error"`. Test: `ServiceWaitTimesReviewTest` › «L4: …».
- **Evidencia:** `resources/views/admin/services/_form.blade.php:60-61`. Ni `<p id="duration_minutes-error">` ni el `<fieldset>` de la línea 37 llevan `aria-describedby`/`aria-invalid` que apunten al error. El resto de errores sí están enlazados a su paso.
- **Impacto:** con un lector de pantalla, al volver con «El servicio entero no puede pasar de 10 horas» el foco no lleva al mensaje y ningún campo se anuncia como inválido. Es un caso raro, porque exige más de 600 minutos.
- **Recomendación:** poner `aria-describedby="duration_minutes-error"` en el `<fieldset>` cuando haya ese error, o añadir ese id al `aria-describedby` de cada paso y marcarlo con `aria-invalid="true"`. Añadir la comprobación a «the form shows the error next to its step».

## L5 · Un registro con esperas incoherentes tumba la agenda y `/reservas` con un 500

- **Severidad:** Baja
- **Estado:** resuelto (T068, decisión del usuario)
- **Resolución:**
  - `TimeProfile::storedWaits()` hace tolerante la lectura de datos guardados en `fromServices()` (cada servicio o copia congelada contra su propia duración) y en `fromAppointment()`. Si las esperas no encajan, se leen como «sin esperas» y se escribe un `Log::warning` con el registro afectado. Así se ocupa más plaza, nunca menos: no hay *overbooking* ni 500.
  - La escritura sigue siendo estricta: `ServiceRequest`, `fromSteps()` y el constructor.
  - `ServiceCatalogSeeder` (`RENAMED_ON_FIRST_RUN`) pone `waits => null` al cambiar la duración.
  - Tests (`ServiceWaitTimesReviewTest`):
    - una cita con una espera que se sale de su duración: la agenda responde 200, la hora se considera ocupada entera y queda el aviso en el registro;
    - un servicio incoherente: `/reservas` responde 200 y el servicio se trata como sin esperas;
    - el *seeder*: el servicio renombrado queda sin esperas.
- **Evidencia:**
  - `app/Booking/TimeProfile.php:49-50` lanza `InvalidArgumentException` al leer.
  - Lo usan `TimeProfile::fromAppointment()` y `fromServices()`, desde `AvailabilityCalculator::busyIntervals()` (línea 294), `AppointmentLaneAssigner::stretches()` (línea 92), `Service::durationWithWaitLabel` y las vistas.
  - Hay al menos una vía en el código que cambia `duration_minutes` sin mirar `waits`: `ServiceCatalogSeeder::run()`, en el bloque `RENAMED_ON_FIRST_RUN`. Hoy es solo local, porque los nombres antiguos no existen en producción, pero lo mismo pasaría con un `UPDATE` hecho a mano para corregir una duración, como sugiere el propio docblock del *seeder*.
- **Impacto:** una sola fila incoherente en `services` o en `appointments` hace fallar la página de reservas de ese servicio, la agenda de ese día y los correos al salón, en lugar de degradarse.
- **Recomendación:** mantener la validación estricta al escribir (`ServiceRequest`, `fromSteps`), pero hacer tolerante la lectura de datos guardados. Por ejemplo, un `TimeProfile::fromStored()` que, si las esperas no encajan, registre un aviso y devuelva el perfil sin esperas. Así se ocupa más plaza, no menos, y nunca hay *overbooking*. Añadir a `ServiceCatalogSeeder` (`RENAMED_ON_FIRST_RUN`) `'waits' => null` y un test que cubra la lectura de un registro incoherente.

---

## Notas de coherencia de la spec y la matriz (sin severidad)

> **Resueltas (T068):** el resumen de `index.md` menciona ya T067 (y T066 y T068), y las filas PRF-159 y PRF-160 citan lo que comprobó el coordinador en Chrome. Siguen en `partial` solo hasta que vuelva a comprobar M1 y L2.

- `.ai/tasks/reservas/index.md:19-20` sigue hablando de «T062–T066» y no menciona T067 (formulario por pasos), que ya está hecha y aparece en la tabla (línea 91). Conviene añadirla al resumen del estado general.
- Las filas PRF-159 y PRF-160 de la matriz (líneas 215-216) siguen en `partial` («pendiente comprobar en el navegador»), aunque el coordinador ya comprobó en Chrome la agenda y el formulario a 390 px y en escritorio. Cuando se resuelvan M1 y L2 y se vuelvan a comprobar, se pueden pasar a `covered`, citando qué se miró.

## Comprobado sin hallazgos

- **Motor (PRF-152/153/157):**
  - el razonamiento de los momentos de `capacityProblem()`;
  - los cierres totales dentro de esperas, que dan `Closed`;
  - la reducción parcial en una espera, que no impide la cita;
  - la regla 1 sobre la duración total;
  - `fittingStartMinutes`, que usa el mismo `hasCapacity`.
- **`RescheduleAppointment` (PRF-156):**
  - sin cambiar los servicios, la cita conserva el perfil congelado;
  - al cambiarlos, los servicios que se mantienen llevan su copia congelada y los nuevos, la suya actual;
  - cambiar las esperas con la misma hora y la misma duración vuelve a comprobar la disponibilidad (con test);
  - «Guardar igualmente» no ha cambiado.
- **Concurrencia:** el bloqueo `lockForUpdate` sobre `booking_settings` se mantiene antes de comprobar e insertar, igual que antes.
- **Migración:** `json()->nullable()->after(...)` es válido en MySQL 5.7+/8. Las tablas son pequeñas, así que reconstruirlas con `AFTER` no es un riesgo. Las citas y los servicios existentes quedan en `null`, que equivale a no tener esperas.
- **Carriles y `DayTimeline` (PRF-110/159):**
  - claves por tramo, con preferencia por el carril anterior;
  - «Sobre capacidad» por tramo;
  - la espera se marca en los carriles de los tramos vecinos, con el texto visible una vez por hueco continuo y en cada franja tocable a través del `aria-label`;
  - las franjas cortas no tocables llevan `aria-hidden`.
- **No revelación (PRF-158):**
  - `/reservas` (pasos 1 y 2), `/cita/{token}` y los 3 correos a la clienta, sin «espera», sin `data-wait-minutes` y sin el JSON;
  - el parcial de correo solo muestra las esperas con `showDuration`.
